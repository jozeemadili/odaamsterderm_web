<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Portal\Branding\Index as Branding;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerBrandingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->merchant = $this->stores['B']->merchant;
        $this->seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->merchant->id]);
        $this->seller->assignRole('merchant_owner');
    }

    public function test_seller_picks_two_colours_that_brand_the_portal_and_store_page(): void
    {
        Livewire::actingAs($this->seller)->test(Branding::class)
            ->call('usePalette', 'Forest & lime')
            ->assertSet('brand_color', '#14532D')
            ->assertSet('brand_accent_color', '#84CC16')
            ->call('saveColor')
            ->assertHasNoErrors();

        $this->merchant->refresh();
        $this->assertSame('#14532D', $this->merchant->brandColor());
        $this->assertSame('#84CC16', $this->merchant->accentColor());

        $this->actingAs($this->seller)->get(route('portal.dashboard'))->assertOk()
            ->assertSee('--brand:#14532D;--brand-ink:#FFFFFF;--brand-accent:#84CC16;--brand-accent-ink:#01162F;', false);

        // Store cards on the marketplace home and the stores API carry the branding.
        $this->get('/')->assertOk()->assertSee('background: #14532D', false)->assertSee('background: #84CC16', false);
        $this->getJson('/api/v1/market/stores')->assertJsonFragment(['brand_color' => '#14532D', 'brand_accent_color' => '#84CC16']);

        $this->get("/stores/{$this->stores['B']->id}")->assertOk()->assertSee('background: #14532D', false)->assertSee('background: #84CC16', false);

        Livewire::actingAs($this->seller)->test(Branding::class)
            ->set('brand_color', '#123456')->set('brand_accent_color', '#123456')
            ->call('saveColor')
            ->assertHasErrors('brand_accent_color');
    }

    public function test_app_can_save_the_accent_colour(): void
    {
        Sanctum::actingAs($this->seller);

        $this->patchJson('/api/v1/merchant', ['brand_color' => '#0C4A6E', 'brand_accent_color' => '#F97316'])
            ->assertOk()
            ->assertJsonPath('data.brand_accent_color', '#F97316')
            ->assertJsonPath('data.brand_text_color', '#FFFFFF');
    }

    public function test_branded_receipts_need_a_signed_link(): void
    {
        $this->merchant->update(['brand_color' => '#7F1D1D', 'brand_accent_color' => '#FDE68A']);
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);
        $customer->assignRole('customer');
        $offer = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail();
        $orders = app(OrderService::class);
        $orders->addToCart($customer, $offer->id, 2);
        $order = $orders->checkout($customer, null, '+255700000123')->first();

        $this->get($order->receiptUrl())
            ->assertOk()
            ->assertSee($this->merchant->business_name)
            ->assertSee($order->reference)
            ->assertSee('--brand:#7F1D1D', false)
            ->assertSee('TZS 87,000');
        $this->get("/receipts/orders/{$order->id}")->assertForbidden();

        $orders->advance($order, Order::ACCEPTED, $this->seller);
        $orders->advance($order->fresh(), Order::PREPARING, $this->seller);
        $orders->advance($order->fresh(), Order::READY, $this->seller);
        $orders->advance($order->fresh(), Order::COMPLETED, $this->seller);
        $sale = SalesRecord::where('order_id', $order->id)->firstOrFail();
        $this->get($sale->receiptUrl())->assertOk()->assertSee('Sales receipt')->assertSee('Sale #'.$sale->id);

        Sanctum::actingAs($customer);
        $this->getJson("/api/v1/customer/orders/{$order->id}")->assertJsonPath('data.receipt_url', fn ($u) => str_contains($u, 'signature='));
    }
}
