<?php

namespace Tests\Feature\Catalog;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreCatalogueStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalogue_pack_is_put_in_the_store_once(): void
    {
        Storage::fake('public');
        $this->artisan('catalog:import-taxonomy')->assertSuccessful();

        $merchant = Merchant::create(['business_name' => 'Amsterdam Group', 'owner_name' => 'Owner', 'phone' => '+255700000000', 'kyc_status' => Merchant::KYC_APPROVED]);
        $store = Branch::create(['merchant_id' => $merchant->id, 'name' => 'TEGETA KIBAONI BRANCH', 'latitude' => -6.66, 'longitude' => 39.21]);

        // Stock the store already has is kept as it is.
        $live = InventoryItem::create([
            'merchant_id' => $merchant->id, 'branch_id' => $store->id,
            'product_variant_id' => ProductVariant::where('sku', 'P034-100G')->value('id'),
            'name' => 'Vitamix Chick', 'unit_price' => 1000,
        ]);

        $this->artisan('catalog:stock-store', ['store' => 'Tegeta Kibaoni'])->assertSuccessful();

        $packs = ProductVariant::count();
        $this->assertSame($packs, InventoryItem::where('branch_id', $store->id)->count());
        $this->assertEquals(1000, $live->fresh()->unit_price);

        // A workbook row: its stock, prices, batch and expiry, listed online.
        $starter = InventoryItem::where('sku', 'P001-25KG')->sole();
        $this->assertEquals(116, $starter->quantity_on_hand);
        $this->assertEquals(42000, $starter->unit_price);
        $this->assertEquals(34100, $starter->unit_cost);
        $this->assertSame('TEG-P00125KG-2602', $starter->batch_no);
        $this->assertNotNull($starter->expiry_date);
        $this->assertTrue($starter->is_marketplace_visible);
        $this->assertEquals(81, $starter->marketplace_quantity);
        $this->assertSame(1, $starter->stockMovements()->where('type', 'in')->count());
        $this->assertSame('Feed', $starter->category->name);

        // A new catalogue pack with no price yet is in the store but not online.
        $alpha = InventoryItem::where('sku', 'P121-1KG')->sole();
        $this->assertNull($alpha->unit_price);
        $this->assertFalse((bool) $alpha->is_marketplace_visible);

        // Running it again adds nothing.
        $this->artisan('catalog:stock-store', ['store' => $store->id])->assertSuccessful();
        $this->assertSame($packs, InventoryItem::where('branch_id', $store->id)->count());
    }
}
