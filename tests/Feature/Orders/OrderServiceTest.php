<?php

namespace Tests\Feature\Orders;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private OrderService $orders;

    private User $customer;

    private User $sellerA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->orders = app(OrderService::class);
        $this->customer = User::registerCustomer(['name' => 'Juma', 'email' => 'juma@example.com', 'phone' => '+255700000099', 'password' => 'secret-pass']);
        $this->sellerA = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
    }

    private function offer(string $store, string $product = 'Chicken Starter'): InventoryItem
    {
        return InventoryItem::where('branch_id', $this->stores[$store]->id)
            ->whereHas('product', fn ($q) => $q->where('name', $product))->firstOrFail();
    }

    public function test_checkout_places_one_order_per_store_and_reserves_units(): void
    {
        Notification::fake();
        $a = $this->offer('A');
        $b = $this->offer('B');

        $this->orders->addToCart($this->customer, $a->id, 10);
        $this->orders->addToCart($this->customer, $this->offer('A', 'Layers Mash')->id, 2);
        $this->orders->addToCart($this->customer, $b->id, 5);

        $orders = $this->orders->checkout($this->customer, 'I will come at 4pm');

        $this->assertCount(2, $orders);
        $orderA = $orders->firstWhere('branch_id', $this->stores['A']->id);
        $this->assertSame(Order::PENDING, $orderA->status);
        $this->assertEquals(10 * 45000 + 2 * 58000, $orderA->total);
        $this->assertSame(2, $orderA->items_count);
        $this->assertStringStartsWith('OH-', $orderA->reference);

        // Physical stock untouched, marketplace quantity moved into reserved.
        $a->refresh();
        $this->assertEquals(100, $a->quantity_on_hand);
        $this->assertEquals(20, $a->marketplace_quantity);
        $this->assertEquals(10, $a->reserved_quantity);
        $this->assertSame($this->customer->id, $orderA->user_id);

        $this->assertSame(0, \App\Models\Cart::for($this->customer)->items()->count());
        Notification::assertSentTo($this->sellerA, OrderPlaced::class);
    }

    public function test_checkout_rechecks_stock_and_places_nothing_if_any_line_fails(): void
    {
        $a = $this->offer('A');
        $b = $this->offer('B');
        $this->orders->addToCart($this->customer, $a->id, 5);
        $this->orders->addToCart($this->customer, $b->id, 20);

        // Seller B sells offline in the meantime: 25 online → only 5 left online.
        app(InventoryService::class)->stockOut($b, 55);

        try {
            $this->orders->checkout($this->customer);
            $this->fail('Expected checkout to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only 5 bag', $e->errors()['cart'][0]);
        }

        $this->assertSame(0, Order::count());
        $this->assertEquals(0, $a->fresh()->reserved_quantity, 'Seller A reservation must be rolled back too.');
        $this->assertSame(2, \App\Models\Cart::for($this->customer)->items()->count());
    }

    public function test_cart_refuses_more_than_marketplace_quantity_or_unlisted_offers(): void
    {
        $this->expectException(ValidationException::class);
        $this->orders->addToCart($this->customer, $this->offer('A')->id, 31);
    }

    public function test_out_of_stock_offers_cannot_be_added(): void
    {
        $this->expectException(ValidationException::class);
        $this->orders->addToCart($this->customer, $this->offer('C')->id, 1);
    }

    public function test_reserved_units_cannot_be_sold_offline(): void
    {
        $a = $this->offer('A');
        $this->orders->addToCart($this->customer, $a->id, 30);
        $this->orders->checkout($this->customer);

        $this->expectException(ValidationException::class);
        app(InventoryService::class)->stockOut($a, 71); // 100 on hand, 30 reserved
    }

    public function test_full_lifecycle_to_pickup_deducts_stock_and_records_online_sale(): void
    {
        Notification::fake();
        $a = $this->offer('A');
        $this->orders->addToCart($this->customer, $a->id, 10);
        $order = $this->orders->checkout($this->customer)->first();

        foreach ([Order::ACCEPTED, Order::PREPARING, Order::READY, Order::COMPLETED] as $status) {
            $order = $this->orders->advance($order, $status, $this->sellerA);
        }

        $a->refresh();
        $this->assertEquals(90, $a->quantity_on_hand);
        $this->assertEquals(0, $a->reserved_quantity);
        $this->assertEquals(20, $a->marketplace_quantity);
        $this->assertNotNull($order->completed_at);

        $sale = SalesRecord::where('order_id', $order->id)->first();
        $this->assertSame(SalesRecord::SOURCE_ONLINE, $sale->source);
        $this->assertEquals(450000, $sale->amount);
        $this->assertEquals(40500, $sale->items->first()->unit_cost); // cost captured for profit reports

        $this->assertSame(['pending', 'accepted', 'preparing', 'ready_for_pickup', 'completed'], $order->events()->pluck('status')->all());
        $this->assertTrue(StockMovement::where('type', StockMovement::TYPE_ONLINE_SALE)->where('inventory_item_id', $a->id)->exists());
        Notification::assertSentToTimes($this->customer, OrderStatusChanged::class, 4);
    }

    public function test_rejecting_requires_a_reason_and_returns_units_to_the_marketplace(): void
    {
        $a = $this->offer('A');
        $this->orders->addToCart($this->customer, $a->id, 30);
        $order = $this->orders->checkout($this->customer)->first();
        $this->assertSame(InventoryItem::MARKET_OUT_OF_STOCK, $a->fresh()->marketplace_status);

        try {
            $this->orders->advance($order, Order::REJECTED, $this->sellerA);
            $this->fail('Reason should be required.');
        } catch (ValidationException) {
        }

        $order = $this->orders->advance($order, Order::REJECTED, $this->sellerA, 'Bags damaged by water');

        $a->refresh();
        $this->assertSame(Order::REJECTED, $order->status);
        $this->assertEquals(0, $a->reserved_quantity);
        $this->assertEquals(30, $a->marketplace_quantity);
        $this->assertSame(InventoryItem::MARKET_ACTIVE, $a->marketplace_status);
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $this->orders->addToCart($this->customer, $this->offer('A')->id, 1);
        $order = $this->orders->checkout($this->customer)->first();

        $this->expectException(ValidationException::class);
        $this->orders->advance($order, Order::COMPLETED, $this->sellerA);
    }

    public function test_customer_can_cancel_until_preparation_starts(): void
    {
        $a = $this->offer('A');
        $this->orders->addToCart($this->customer, $a->id, 4);
        $order = $this->orders->checkout($this->customer)->first();
        $order = $this->orders->advance($order, Order::ACCEPTED, $this->sellerA);

        $order = $this->orders->cancelByCustomer($order, $this->customer, 'Bought elsewhere');
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertEquals(0, $a->fresh()->reserved_quantity);

        $this->orders->addToCart($this->customer, $a->id, 1);
        $second = $this->orders->checkout($this->customer)->first();
        $this->orders->advance($second, Order::ACCEPTED, $this->sellerA);
        $second = $this->orders->advance($second, Order::PREPARING, $this->sellerA);

        $this->expectException(ValidationException::class);
        $this->orders->cancelByCustomer($second, $this->customer);
    }
}
