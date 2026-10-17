<?php

namespace Tests\Feature\Market;

use App\Livewire\Auth\CustomerRegister;
use App\Livewire\Market\Account;
use App\Livewire\Market\Home;
use App\Livewire\Market\LocationBar;
use App\Livewire\Market\ProductShow;
use App\Models\User;
use App\Support\MarketLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceWebTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_guests_can_browse_the_marketplace(): void
    {
        $this->get('/')->assertOk()->assertSee('Chicken Starter 25KG')->assertSee('Set your location');
        $this->get('/products/chicken-starter-25kg')->assertOk()->assertSee('Seller B')->assertSee('Out of stock');
        $this->get("/stores/{$this->stores['A']->id}")->assertOk()->assertSee('Layers Mash 50KG');
    }

    public function test_unknown_or_private_pages_return_404(): void
    {
        $this->get('/products/does-not-exist')->assertNotFound();

        $this->stores['B']->update(['status' => 'suspended']);
        $this->get("/stores/{$this->stores['B']->id}")->assertNotFound();
    }

    public function test_picking_a_location_ranks_by_distance(): void
    {
        Livewire::test(LocationBar::class)
            ->call('openPicker')
            ->set('latitude', $this->customerLat)
            ->set('longitude', $this->customerLng)
            ->set('label', 'Sinza')
            ->call('savePicked')
            ->assertDispatched('market-location-updated');

        $this->assertSame('Sinza', MarketLocation::current()->label);

        Livewire::test(ProductShow::class, ['product' => $this->chickenStarter()])
            ->assertSet('sort', 'nearest')
            ->assertSeeInOrder(['Seller A', '1.2 km', 'Seller B', 'Seller C']);
    }

    public function test_search_and_filters_on_home(): void
    {
        Livewire::test(Home::class)
            ->assertSet('sort', 'price') // no location yet
            ->set('q', 'tilapia')
            ->assertSee('Tilapia Floating Feed')
            ->assertDontSee('Layers Mash 50KG')
            ->set('q', '')
            ->set('inStock', true)
            ->set('maxPrice', '50000')
            ->assertSee('Chicken Starter 25KG')
            ->assertDontSee('Tilapia Floating Feed');
    }

    public function test_customer_signs_up_and_lands_on_the_marketplace(): void
    {
        Livewire::test(CustomerRegister::class)
            ->set('name', 'Juma Mwinyi')
            ->set('phone', '+255700000099')
            ->set('email', 'juma@example.com')
            ->set('password', 'secret-pass')
            ->set('password_confirmation', 'secret-pass')
            ->call('register')
            ->assertRedirect(route('market.home'));

        $user = User::where('email', 'juma@example.com')->first();
        $this->assertTrue($user->isCustomer());
        $this->assertAuthenticatedAs($user);
        $this->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_customer_saves_default_location_on_account_page(): void
    {
        $customer = User::registerCustomer(['name' => 'Juma', 'email' => 'juma@example.com', 'password' => 'secret-pass']);

        Livewire::actingAs($customer)->test(Account::class)
            ->set('phone', '+255700000099')
            ->set('latitude', $this->customerLat)
            ->set('longitude', $this->customerLng)
            ->set('location_label', 'My farm')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('My farm', $customer->fresh()->location_label);
    }

    public function test_account_page_is_for_customers_only(): void
    {
        $this->get(route('market.account'))->assertRedirect(route('login'));

        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        $this->actingAs($seller)->get(route('market.account'))->assertForbidden();
    }
}
