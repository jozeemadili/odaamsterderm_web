<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Marketplace\Exceptions;
use App\Livewire\Admin\Reports\Marketplace as MarketplaceReport;
use App\Livewire\Admin\Stores\Index as StoresIndex;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\StoreStatusChanged;
use App\Services\MarketplaceMonitor;
use App\Services\MarketplaceSearch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceMonitoringTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
        $this->customer = User::registerCustomer(['name' => 'Juma', 'email' => 'juma@example.com', 'phone' => '+255700000099', 'password' => 'secret-pass']);
    }

    private function offerA(): InventoryItem
    {
        return InventoryItem::where('branch_id', $this->stores['A']->id)
            ->whereHas('product', fn ($q) => $q->where('name', 'Chicken Starter'))->firstOrFail();
    }

    private function exception(string $key): array
    {
        return collect(app(MarketplaceMonitor::class)->exceptions())->firstWhere('key', $key);
    }

    public function test_admin_pages_render_and_respect_permissions(): void
    {
        foreach (['admin.marketplace.index', 'admin.marketplace.exceptions', 'admin.orders.index', 'admin.stores.index', 'admin.reports.marketplace', 'admin.dashboard'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }

        $finance = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $finance->assignRole('finance_ops');
        $this->actingAs($finance)->get(route('admin.marketplace.index'))->assertForbidden();

        $support = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $support->assignRole('support_agent');
        $this->actingAs($support)->get(route('admin.orders.index'))->assertOk();
    }

    public function test_suspending_a_store_hides_it_notifies_the_seller_and_is_audited(): void
    {
        Notification::fake();
        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);

        Livewire::actingAs($this->admin)->test(StoresIndex::class)
            ->set('suspendingId', $this->stores['A']->id)
            ->call('suspend')
            ->assertHasErrors('reason')
            ->set('reason', 'Unregistered veterinary drugs')
            ->call('suspend')
            ->assertHasNoErrors();

        $store = $this->stores['A']->fresh();
        $this->assertFalse($store->isActive());
        $this->assertSame('Unregistered veterinary drugs', $store->suspension_reason);
        $this->assertFalse(app(MarketplaceSearch::class)->isPublicStore($store->load('merchant')));
        Notification::assertSentTo($seller, StoreStatusChanged::class);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $store->id, 'log_name' => 'store']);

        Livewire::actingAs($this->admin)->test(StoresIndex::class)->call('reactivate', $store->id);
        $this->assertTrue($store->fresh()->isActive());
    }

    public function test_exception_checks_detect_inconsistent_stock_and_reservations(): void
    {
        $this->assertSame(0, $this->exception('reservation_drift')['count']);

        // Simulate corrupted data that bypassed InventoryService.
        $this->offerA()->update(['reserved_quantity' => 120, 'marketplace_quantity' => 30]);

        $this->assertSame(1, $this->exception('reserved_exceeds_stock')['count']);
        $this->assertSame(1, $this->exception('reservation_drift')['count']);
        $this->assertSame(1, $this->exception('marketplace_exceeds_available')['count']);
    }

    public function test_real_orders_do_not_trigger_reservation_drift(): void
    {
        $orders = app(OrderService::class);
        $orders->addToCart($this->customer, $this->offerA()->id, 4);
        $orders->checkout($this->customer);

        $this->assertEquals(4, $this->offerA()->reserved_quantity);
        $this->assertSame(0, $this->exception('reservation_drift')['count']);
    }

    public function test_stale_orders_and_hidden_listings_are_flagged(): void
    {
        $orders = app(OrderService::class);
        $orders->addToCart($this->customer, $this->offerA()->id, 1);
        $order = $orders->checkout($this->customer)->first();
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(30)]);

        $this->assertSame(1, $this->exception('stale_pending_orders')['count']);

        // Seller left listings on, but the store lost its location.
        $this->stores['B']->update(['latitude' => null]);
        $this->assertSame(1, $this->exception('hidden_listings')['count']);
    }

    public function test_admin_can_hide_a_broken_listing(): void
    {
        $offer = $this->offerA();
        $offer->update(['marketplace_quantity' => 500]); // more than stock

        Livewire::actingAs($this->admin)->test(Exceptions::class)
            ->assertSee('Marketplace quantity above unreserved stock')
            ->call('hideListing', $offer->id);

        $offer->refresh();
        $this->assertFalse($offer->is_marketplace_visible);
        $this->assertEquals(100, $offer->marketplace_quantity);
        $this->assertEquals(100, $offer->quantity_on_hand);
    }

    public function test_report_counts_gmv_fulfilment_and_top_products(): void
    {
        $orders = app(OrderService::class);
        $sellerA = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);

        $orders->addToCart($this->customer, $this->offerA()->id, 2);
        $done = $orders->checkout($this->customer)->first();
        foreach ([Order::ACCEPTED, Order::READY, Order::COMPLETED] as $s) {
            $done = $orders->advance($done, $s, $sellerA);
        }

        $orders->addToCart($this->customer, $this->offerA()->id, 1);
        $rejected = $orders->checkout($this->customer)->first();
        $orders->advance($rejected, Order::REJECTED, $sellerA, 'Bags damaged');

        $report = app(MarketplaceMonitor::class)->report(now()->subDays(6), now());

        $this->assertSame(2, $report['orders_placed']);
        $this->assertSame(1, $report['orders_completed']);
        $this->assertEquals(90000, $report['gmv']);
        $this->assertEquals(50.0, $report['fulfilment_rate']);
        $this->assertSame(['Bags damaged' => 1], $report['rejection_reasons']);
        $this->assertSame('Chicken Starter 25KG', $report['top_products']->first()->name);
        $this->assertEquals(90000, $report['online_sales']);
        $this->assertSame('Seller A', $report['top_sellers']->first()->seller);

        Livewire::actingAs($this->admin)->test(MarketplaceReport::class)
            ->call('preset', 7)
            ->assertSee('TZS 90,000')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }
}
