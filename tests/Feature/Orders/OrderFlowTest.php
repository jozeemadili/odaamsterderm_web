<?php

namespace Tests\Feature\Orders;

use App\Livewire\Market\Cart as CartPage;
use App\Livewire\Market\OrderShow;
use App\Livewire\Market\ProductShow;
use App\Livewire\Portal\Orders\Show as SellerOrderShow;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $customer;

    private User $sellerA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->customer = User::registerCustomer(['name' => 'Juma', 'email' => 'juma@example.com', 'phone' => '+255700000099', 'password' => 'secret-pass']);
        $this->sellerA = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        $this->sellerA->assignRole('merchant_owner');
    }

    private function offerA(): InventoryItem
    {
        return InventoryItem::where('branch_id', $this->stores['A']->id)
            ->whereHas('product', fn ($q) => $q->where('name', 'Chicken Starter'))->firstOrFail();
    }

    public function test_web_customer_adds_to_cart_checks_out_and_seller_fulfils(): void
    {
        $offer = $this->offerA();

        Livewire::actingAs($this->customer)->test(ProductShow::class, ['product' => $this->chickenStarter()])
            ->set("quantities.{$offer->id}", '3')
            ->call('addToCart', $offer->id)
            ->assertSet('cartMessage', 'Added to your cart.')
            ->assertDispatched('cart-updated');

        Livewire::actingAs($this->customer)->test(CartPage::class)
            ->assertSee('Pickup at Seller A Store')
            ->assertSee('135,000')
            ->set('note', 'Coming at 4pm')
            ->call('checkout')
            ->assertRedirect();

        $order = Order::first();
        $this->assertSame('Coming at 4pm', $order->customer_note);

        Livewire::actingAs($this->sellerA)->test(SellerOrderShow::class, ['order' => $order])
            ->call('advance', 'accepted')
            ->call('advance', 'ready_for_pickup')
            ->call('advance', 'completed')
            ->assertHasNoErrors();

        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
        $this->assertEquals(97, $offer->fresh()->quantity_on_hand);

        Livewire::actingAs($this->customer)->test(OrderShow::class, ['order' => $order])
            ->assertSee('Completed')
            ->assertSee($order->reference);
    }

    public function test_guests_are_sent_to_sign_in_and_sellers_cannot_order(): void
    {
        $offer = $this->offerA();

        Livewire::test(ProductShow::class, ['product' => $this->chickenStarter()])
            ->call('addToCart', $offer->id)
            ->assertRedirect(route('login'));

        Livewire::actingAs($this->sellerA)->test(ProductShow::class, ['product' => $this->chickenStarter()])
            ->call('addToCart', $offer->id)
            ->assertHasErrors('cart');
    }

    public function test_customers_only_see_their_own_orders_and_sellers_only_theirs(): void
    {
        app(OrderService::class)->addToCart($this->customer, $this->offerA()->id, 1);
        $order = app(OrderService::class)->checkout($this->customer)->first();

        $other = User::registerCustomer(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret-pass']);
        $this->actingAs($other)->get(route('market.order', $order))->assertNotFound();

        $sellerB = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['B']->merchant_id]);
        $sellerB->assignRole('merchant_owner');
        $this->actingAs($sellerB)->get(route('portal.orders.show', $order))->assertForbidden();

        $this->actingAs($this->sellerA)->get(route('portal.orders.index'))->assertOk()->assertSee($order->reference);
        $this->actingAs($this->sellerA)->get(route('portal.dashboard'))->assertOk()->assertSee('New orders');
    }

    public function test_api_cart_checkout_and_seller_status_flow(): void
    {
        Sanctum::actingAs($this->customer);
        $offer = $this->offerA();

        $this->postJson('/api/v1/customer/cart/items', ['offer_id' => $offer->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 90000)
            ->assertJsonPath('stores.0.lines.0.quantity', 2)
            ->assertJsonPath('stores.0.store.name', 'Seller A Store')
            ->assertJsonMissingPath('stores.0.store.offers_count'); // conditional fields are left out, not {}

        $this->putJson("/api/v1/customer/cart/items/{$offer->id}", ['quantity' => 40])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $orderId = $this->postJson('/api/v1/customer/checkout', ['note' => 'Morning pickup'])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.can_cancel', true)
            ->assertJsonMissingPath('data.0.customer')
            ->json('data.0.id');

        Sanctum::actingAs($this->sellerA);
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.customer.name', 'Juma');
        $this->postJson("/api/v1/orders/{$orderId}/status", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.next_statuses.0.status', 'preparing');
        $this->postJson("/api/v1/orders/{$orderId}/status", ['status' => 'completed'])->assertUnprocessable();

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.status', 'accepted');
        $this->getJson("/api/v1/customer/orders/{$orderId}")->assertOk()->assertJsonCount(2, 'data.events');
        $this->postJson("/api/v1/customer/orders/{$orderId}/cancel", ['reason' => 'Changed plans'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        Sanctum::actingAs($this->sellerA);
        $this->getJson('/api/v1/dashboard')->assertJsonPath('marketplace.orders.pending', 0);
    }
}
