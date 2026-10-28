<?php

namespace Tests\Feature\Marketplace;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private ProductVariant $small;

    private ProductVariant $large;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();

        // Chicken Starter came in as one 25KG pack; a 50KG pack is added, as
        // the workbook import does, so the pack moves to the variants.
        $product = $this->chickenStarter();
        $this->small = $product->variants()->sole();
        $this->large = $product->variants()->create(['sku' => 'P001-50KG', 'name' => '50 kg bag', 'selling_unit' => 'bag', 'sort_order' => 1]);
        $this->small->update(['sku' => 'P001-25KG', 'name' => '25 kg bag']);
        $product->update(['pack_size' => null]);
    }

    public function test_stock_of_a_one_pack_product_is_linked_to_that_pack(): void
    {
        $this->assertSame(3, InventoryItem::where('product_variant_id', $this->small->id)->count());
        $this->assertSame('Chicken Starter - 25 kg bag', InventoryItem::where('product_variant_id', $this->small->id)->first()->offerName());
    }

    public function test_buyers_compare_one_pack_size_at_a_time(): void
    {
        $store = $this->stores['B'];
        $bigBag = InventoryItem::create([
            'merchant_id' => $store->merchant_id, 'branch_id' => $store->id, 'product_id' => $this->chickenStarter()->id,
            'name' => 'Chicken Starter 50KG', 'unit_price' => 82000,
        ]);
        app(InventoryService::class)->stockIn($bigBag, 40);
        $this->assertNull($bigBag->product_variant_id); // two packs: the seller must choose
        $this->assertContains('Choose which pack size of the catalog product this is.', $bigBag->marketplaceBlockers());

        $bigBag->update(['product_variant_id' => $this->large->id]);
        app(InventoryService::class)->updateMarketplace($bigBag->fresh(), true, 40);

        $product = $this->chickenStarter();
        $this->getJson("/api/v1/market/products/{$product->id}?lat={$this->customerLat}&lng={$this->customerLng}")
            ->assertOk()
            ->assertJsonCount(2, 'product.variants')
            ->assertJsonPath('product.variants.1.sku', 'P001-50KG')
            ->assertJsonCount(4, 'offers');

        $this->getJson("/api/v1/market/products/{$product->id}?variant_id={$this->large->id}")
            ->assertOk()
            ->assertJsonCount(1, 'offers')
            ->assertJsonPath('offers.0.name', 'Chicken Starter - 50 kg bag')
            ->assertJsonPath('offers.0.variant.name', '50 kg bag')
            ->assertJsonPath('offers.0.price', 82000);

        $this->get(route('market.product', $product))
            ->assertOk()
            ->assertSee('Pack size:')
            ->assertSee('50 kg bag');
    }

    public function test_seller_api_stocks_a_pack_size_with_its_batch(): void
    {
        $seller = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        $seller->assignRole('merchant_owner');
        Sanctum::actingAs($seller);

        $payload = [
            'name' => 'Chicken Starter big bag',
            'category_id' => InventoryCategory::create(['merchant_id' => $this->stores['A']->merchant_id, 'name' => 'Feeds'])->id,
            'branch_id' => $this->stores['A']->id,
            'product_variant_id' => $this->large->id,
            'batch_no' => 'B-2026-10',
        ];

        $id = $this->postJson('/api/v1/inventory', $payload)
            ->assertCreated()
            ->assertJsonPath('data.variant.sku', 'P001-50KG')
            ->assertJsonPath('data.batch_no', 'B-2026-10')
            ->json('data.id');

        // The pack decides the product.
        $this->assertSame($this->chickenStarter()->id, InventoryItem::find($id)->product_id);

        // A pack of another product is refused.
        $layersMash = Product::where('name', 'Layers Mash')->first();
        $this->postJson('/api/v1/inventory', ['product_id' => $layersMash->id] + $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_variant_id');
    }
}
