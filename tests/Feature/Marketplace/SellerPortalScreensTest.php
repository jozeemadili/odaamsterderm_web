<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Portal\Catalog\Index as CatalogIndex;
use App\Livewire\Portal\Inventory\Index as InventoryIndex;
use App\Livewire\Portal\Inventory\Show as InventoryShow;
use App\Livewire\Portal\Marketplace\Index as MarketplaceIndex;
use App\Livewire\Portal\Stores\Show as StoreShow;
use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class SellerPortalScreensTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSeller();
    }

    public function test_seller_pages_render(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 100);
        app(InventoryService::class)->updateMarketplace($item, true, 30);

        $this->actingAs($this->owner);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Listed products');
        $this->get(route('portal.marketplace.index'))->assertOk()->assertSee('Chicken Starter 25KG');
        $this->get(route('portal.catalog.index'))->assertOk()->assertSee('Layers Mash 50KG');
        $this->get(route('portal.stores.show', $this->store))->assertOk()->assertSee('Open in Google Maps');
        $this->get(route('portal.inventory.show', $item))->assertOk()->assertSee('Marketplace quantity');
        $this->get(route('portal.inventory.index'))->assertOk()->assertSee('30 online');
    }

    public function test_seller_sets_store_location_and_hours(): void
    {
        $store = Branch::create(['merchant_id' => $this->merchant->id, 'name' => 'Tegeta']);

        Livewire::actingAs($this->owner)->test(StoreShow::class, ['store' => $store])
            ->assertSet('editing', true)
            ->set('latitude', -6.65)
            ->set('longitude', 39.2)
            ->set('pickup_instructions', 'Back gate')
            ->set('hours.sun.closed', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false);

        $store->refresh();
        $this->assertTrue($store->hasLocation());
        $this->assertNull($store->opening_hours['sun']);
        $this->assertSame('08:00', $store->opening_hours['mon']['open']);
    }

    public function test_seller_cannot_open_another_sellers_store(): void
    {
        $other = \App\Models\Merchant::create(['business_name' => 'Other', 'owner_name' => 'X', 'phone' => '+255711111111']);
        $theirs = Branch::create(['merchant_id' => $other->id, 'name' => 'Theirs']);

        $this->actingAs($this->owner)->get(route('portal.stores.show', $theirs))->assertForbidden();
    }

    public function test_add_item_linked_to_catalog_product_prefills_name_and_unit(): void
    {
        $product = Product::where('name', 'Dairy Meal')->first();
        $category = \App\Models\InventoryCategory::create(['merchant_id' => $this->merchant->id, 'name' => 'Feeds']);

        Livewire::actingAs($this->owner)->test(InventoryIndex::class)
            ->set('catalogSearch', 'dairy')
            ->assertCount('catalogMatches', 1)
            ->call('selectCatalogProduct', $product->id)
            ->assertSet('name', 'Dairy Meal 70KG')
            ->assertSet('unit', 'bag')
            ->set('category_id', (string) $category->id)
            ->set('branch_id', (string) $this->store->id)
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inventory_items', ['name' => 'Dairy Meal 70KG', 'product_id' => $product->id]);
    }

    public function test_marketplace_screen_saves_and_reports_errors(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 12);

        Livewire::actingAs($this->owner)->test(MarketplaceIndex::class)
            ->call('edit', $item->id)
            ->call('exposeAll')
            ->assertSet('quantity', '12')
            ->set('quantity', '20')
            ->call('save')
            ->assertHasErrors('quantity')
            ->set('quantity', '12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(12, $item->fresh()->marketplace_quantity);
    }

    public function test_item_page_links_catalog_and_shows_blockers(): void
    {
        $item = $this->createItem(['product_id' => null]);
        app(InventoryService::class)->stockIn($item, 5);

        Livewire::actingAs($this->owner)->test(InventoryShow::class, ['item' => $item])
            ->assertSee('Link this item to a catalog product.')
            ->set('mpVisible', true)
            ->set('mpQuantity', '5')
            ->call('saveMarketplace')
            ->assertHasErrors('mpQuantity')
            ->call('selectCatalogProduct', Product::where('name', 'Chicken Starter')->first()->id)
            ->call('saveCatalogLink')
            ->assertSet('productLabel', 'Chicken Starter 25KG')
            ->set('mpVisible', true)
            ->call('saveMarketplace')
            ->assertHasNoErrors();

        $this->assertTrue($item->fresh()->is_marketplace_visible);
    }

    public function test_adjustment_from_inventory_screen_needs_reason(): void
    {
        $item = $this->createItem();
        app(InventoryService::class)->stockIn($item, 20);

        Livewire::actingAs($this->owner)->test(InventoryIndex::class)
            ->call('startMovement', $item->id)
            ->set('movementType', 'adjustment')
            ->set('movementQuantity', '18')
            ->call('saveMovement')
            ->assertHasErrors('movementReason')
            ->set('movementReason', 'Stock count')
            ->call('saveMovement')
            ->assertHasNoErrors();

        $this->assertEquals(18, $item->fresh()->quantity_on_hand);
    }

    public function test_seller_proposes_a_catalog_product(): void
    {
        Livewire::actingAs($this->owner)->test(CatalogIndex::class)
            ->set('form.name', 'Calf Milk Replacer')
            ->set('form.pack_size', '20KG')
            ->set('form.unit', 'bag')
            ->set('form.animal_type_id', (string) AnimalType::where('slug', 'cattle')->first()->id)
            ->set('form.product_category_id', (string) ProductCategory::where('slug', 'feed')->first()->id)
            ->call('propose')
            ->assertHasNoErrors()
            ->assertSee('Waiting for review');

        $this->assertDatabaseHas('products', [
            'name' => 'Calf Milk Replacer',
            'status' => 'pending',
            'proposed_by_merchant_id' => $this->merchant->id,
        ]);
    }
}
