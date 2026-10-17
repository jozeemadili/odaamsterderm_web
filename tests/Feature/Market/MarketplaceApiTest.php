<?php

namespace Tests\Feature\Market;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function near(): string
    {
        return "lat={$this->customerLat}&lng={$this->customerLng}";
    }

    public function test_public_product_search_needs_no_sign_in(): void
    {
        $this->getJson('/api/v1/market/products?'.$this->near().'&search=starter')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Chicken Starter 25KG')
            ->assertJsonPath('data.0.min_price', 43500)
            ->assertJsonPath('data.0.sellers_count', 3)
            ->assertJsonPath('data.0.nearest_label', '1.2 km')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_product_comparison_matches_the_requirements_example(): void
    {
        $offers = $this->getJson("/api/v1/market/products/{$this->chickenStarter()->id}?".$this->near())
            ->assertOk()
            ->assertJsonPath('product.display_name', 'Chicken Starter 25KG')
            ->json('offers');

        $this->assertSame(['Seller A', 'Seller B', 'Seller C'], array_column(array_column($offers, 'seller'), 'name'));
        $this->assertSame([45000, 43500, 44000], array_map(fn ($o) => (int) $o['price'], $offers));
        $this->assertSame([30, 25, 0], array_map(fn ($o) => (int) $o['available_quantity'], $offers));
        $this->assertSame(['Available', 'Available', 'Out of stock'], array_column($offers, 'status_label'));
        $this->assertSame('1.2 km', $offers[0]['distance_label']);
        $this->assertArrayNotHasKey('quantity_on_hand', $offers[0], 'Physical stock must stay private.');
    }

    public function test_unapproved_products_and_private_stores_are_not_found(): void
    {
        $pending = Product::create([
            'name' => 'Secret Feed', 'animal_type_id' => 1, 'product_category_id' => 1,
            'unit' => 'bag', 'status' => Product::STATUS_PENDING, 'slug' => 'secret-feed',
        ]);

        $this->getJson("/api/v1/market/products/{$pending->id}")->assertNotFound();

        $this->stores['A']->update(['is_marketplace_enabled' => false]);
        $this->getJson("/api/v1/market/stores/{$this->stores['A']->id}")->assertNotFound();
    }

    public function test_stores_and_filters_endpoints(): void
    {
        $this->getJson('/api/v1/market/stores?'.$this->near())
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Seller A Store')
            ->assertJsonPath('data.0.distance_label', '1.2 km');

        $this->getJson("/api/v1/market/stores/{$this->stores['C']->id}?".$this->near())
            ->assertOk()
            ->assertJsonPath('store.seller.name', 'Seller C')
            ->assertJsonCount(2, 'offers');

        $this->getJson('/api/v1/market/filters')
            ->assertOk()
            ->assertJsonPath('animal_types.0.name', 'Poultry')
            ->assertJsonCount(3, 'sellers');
    }

    public function test_invalid_location_is_rejected(): void
    {
        $this->getJson('/api/v1/market/products?lat=200&lng=39')->assertUnprocessable()->assertJsonValidationErrors('lat');
    }

    public function test_customer_registers_saves_location_and_is_kept_out_of_seller_api(): void
    {
        $token = $this->postJson('/api/v1/auth/register-customer', [
            'name' => 'Juma Mwinyi',
            'phone' => '+255700000099',
            'email' => 'juma@example.com',
            'password' => 'secret-pass',
        ])->assertCreated()
            ->assertJsonPath('user.user_type', 'customer')
            ->assertJsonPath('merchant', null)
            ->json('token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->patchJson('/api/v1/customer/profile', ['latitude' => -6.779, 'longitude' => 39.235, 'location_label' => 'Sinza'], $headers)
            ->assertOk()
            ->assertJsonPath('data.location.label', 'Sinza');

        $this->getJson('/api/v1/inventory', $headers)->assertForbidden();
        $this->assertTrue(User::where('email', 'juma@example.com')->first()->hasRole('customer'));
    }

    public function test_sellers_cannot_use_customer_endpoints(): void
    {
        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/customer/profile')->assertForbidden();
    }
}
