<?php

namespace Tests\Feature\Marketplace;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class SellerMarketplaceApiTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSeller();
        Sanctum::actingAs($this->owner);
    }

    public function test_pos_checkout_goes_through_the_inventory_service(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 100);
        app(InventoryService::class)->updateMarketplace($item, true, 30);

        $this->postJson('/api/v1/pos/checkout', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 80]],
        ])->assertSuccessful();

        $item->refresh();
        $this->assertEquals(20, $item->quantity_on_hand);
        $this->assertEquals(20, $item->marketplace_quantity);

        $sale = StockMovement::where('reference', 'sale')->first();
        $this->assertEquals(20, $sale->balance_after);
        $this->assertSame($this->owner->id, $sale->recorded_by);
    }

    public function test_pos_checkout_cannot_sell_reserved_stock(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 10);
        $item->update(['reserved_quantity' => 8]);

        $this->postJson('/api/v1/pos/checkout', [
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 5]],
        ])->assertUnprocessable();

        $this->assertEquals(10, $item->fresh()->quantity_on_hand);
    }

    public function test_seller_updates_marketplace_settings(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 100);

        $this->patchJson("/api/v1/inventory/{$item->id}/marketplace", [
            'is_marketplace_visible' => true,
            'marketplace_quantity' => 30,
        ])->assertOk()
            ->assertJsonPath('data.marketplace.is_visible', true)
            ->assertJsonPath('data.marketplace.quantity', 30)
            ->assertJsonPath('data.marketplace.status', 'active')
            ->assertJsonPath('data.marketplace.blockers', [])
            ->assertJsonPath('data.product.display_name', 'Chicken Starter 25KG');

        $this->patchJson("/api/v1/inventory/{$item->id}/marketplace", [
            'is_marketplace_visible' => true,
            'marketplace_quantity' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors('marketplace_quantity');
    }

    public function test_adjustment_requires_reason_and_sets_counted_quantity(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 20);

        $this->postJson("/api/v1/inventory/{$item->id}/stock-movement", ['type' => 'adjustment', 'quantity' => 18])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson("/api/v1/inventory/{$item->id}/stock-movement", ['type' => 'adjustment', 'quantity' => 18, 'reason' => 'Two torn bags'])
            ->assertOk()->assertJsonPath('data.quantity_on_hand', 18);
    }

    public function test_items_cannot_be_assigned_to_another_sellers_store(): void
    {
        $other = Merchant::create(['business_name' => 'Other', 'owner_name' => 'X', 'phone' => '+255711111111']);
        $otherStore = Branch::create(['merchant_id' => $other->id, 'name' => 'Theirs']);
        $item = $this->createItem();

        $this->patchJson("/api/v1/inventory/{$item->id}", ['branch_id' => $otherStore->id])
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_opening_stock_on_create_is_logged_as_a_movement(): void
    {
        $item = $this->createItem();

        $response = $this->postJson('/api/v1/inventory', [
            'name' => 'Chicken Grower 25KG',
            'category_id' => $item->category_id,
            'branch_id' => $this->store->id,
            'product_id' => Product::where('name', 'Chicken Grower')->first()->id,
            'quantity_on_hand' => 40,
        ])->assertCreated()->assertJsonPath('data.quantity_on_hand', 40);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $response->json('data.id'),
            'reference' => 'opening_stock',
            'balance_after' => 40,
        ]);
    }

    public function test_unlinking_catalog_product_hides_the_listing(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 10);
        app(InventoryService::class)->updateMarketplace($item, true, 10);

        $this->patchJson("/api/v1/inventory/{$item->id}", ['product_id' => null])
            ->assertOk()->assertJsonPath('data.marketplace.is_visible', false);
    }

    public function test_store_location_and_hours_can_be_updated(): void
    {
        $this->patchJson("/api/v1/branches/{$this->store->id}", [
            'latitude' => -6.8,
            'longitude' => 39.28,
            'pickup_instructions' => 'Gate 2',
            'opening_hours' => ['mon' => ['open' => '08:00', 'close' => '17:00'], 'sun' => null],
        ])->assertOk()
            ->assertJsonPath('data.has_location', true)
            ->assertJsonPath('data.opening_hours.mon.close', '17:00')
            ->assertJsonPath('data.pickup_instructions', 'Gate 2');

        $this->patchJson("/api/v1/branches/{$this->store->id}", ['latitude' => 120, 'longitude' => 39])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_catalog_lists_approved_products_and_accepts_proposals(): void
    {
        $this->getJson('/api/v1/catalog/animal-types')->assertOk()->assertJsonPath('data.0.name', 'Poultry');
        $this->getJson('/api/v1/catalog/products?search=starter')->assertOk()->assertJsonPath('data.0.display_name', 'Chicken Starter 25KG');

        $chicken = AnimalType::where('slug', 'chicken')->first();
        // Any category can be used for any animal, so the picker lists them all.
        $cats = $this->getJson("/api/v1/catalog/categories?animal_type_id={$chicken->id}")->assertOk()->json('data');
        $this->assertContains('Vaccines & biologicals', array_column($cats, 'name'));
        $this->assertContains('Live animals & genetics', array_column($cats, 'name'));

        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Broiler Booster',
            'pack_size' => '1KG',
            'unit' => 'sachet',
            'animal_type_id' => $chicken->id,
            'product_category_id' => ProductCategory::where('code', 'C2010')->first()->id,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        // The proposer sees their pending proposal; other sellers don't.
        $this->getJson('/api/v1/catalog/products?search=booster')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/catalog/proposals')->assertJsonCount(1, 'data');
    }

    public function test_dashboard_reports_marketplace_and_stock_metrics(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 100);
        app(InventoryService::class)->updateMarketplace($item, true, 30);

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('marketplace.listed_count', 1)
            ->assertJsonPath('marketplace.units_exposed', 30)
            ->assertJsonPath('stock_summary.total_quantity', 100)
            ->assertJsonPath('stock_summary.buying_value', 4100000);
    }
}
