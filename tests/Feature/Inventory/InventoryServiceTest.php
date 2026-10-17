<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSeller();
        $this->inventory = app(InventoryService::class);
    }

    public function test_stock_in_increases_stock_and_logs_balances_without_exposing_more_online(): void
    {
        $item = $this->createItem();

        $movement = $this->inventory->stockIn($item, 100, ['reference' => 'purchase_receipt']);

        $this->assertEquals(100, $item->quantity_on_hand);
        $this->assertEquals(0, $item->marketplace_quantity);
        $this->assertSame(StockMovement::TYPE_IN, $movement->type);
        $this->assertEquals(100, $movement->balance_after);
        $this->assertSame($this->merchant->id, $movement->merchant_id);
    }

    public function test_stock_in_can_expose_part_of_the_new_stock(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 100);
        $this->inventory->updateMarketplace($item, true, 30);

        $this->inventory->stockIn($item, 20, [], exposeToMarketplace: 50);

        // Never exposes more than was just received.
        $this->assertEquals(120, $item->quantity_on_hand);
        $this->assertEquals(50, $item->marketplace_quantity);
    }

    public function test_offline_sale_keeps_marketplace_quantity_when_enough_stock_remains(): void
    {
        // Requirements example: 100 bags in store, 30 exposed online.
        $item = $this->createItem();
        $this->inventory->stockIn($item, 100);
        $this->inventory->updateMarketplace($item, true, 30);

        $this->inventory->stockOut($item, 50);

        $this->assertEquals(50, $item->quantity_on_hand);
        $this->assertEquals(30, $item->marketplace_quantity);
        $this->assertSame(InventoryItem::MARKET_ACTIVE, $item->marketplace_status);
    }

    public function test_offline_sale_shrinks_marketplace_quantity_when_stock_runs_short(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 100);
        $this->inventory->updateMarketplace($item, true, 30);

        $movement = $this->inventory->stockOut($item, 80);

        $this->assertEquals(20, $item->quantity_on_hand);
        $this->assertEquals(20, $item->marketplace_quantity);
        $this->assertEquals(20, $movement->marketplace_quantity_after);
    }

    public function test_selling_out_marks_the_listing_out_of_stock_and_restocking_does_not_relist_automatically(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 10);
        $this->inventory->updateMarketplace($item, true, 10);

        $this->inventory->stockOut($item, 10);
        $this->assertSame(InventoryItem::MARKET_OUT_OF_STOCK, $item->marketplace_status);
        $this->assertEquals(0, $item->marketplace_quantity);

        // Restocking increases actual stock only; the seller chooses what to expose.
        $this->inventory->stockIn($item, 40);
        $this->assertEquals(0, $item->marketplace_quantity);
        $this->assertSame(InventoryItem::MARKET_OUT_OF_STOCK, $item->marketplace_status);

        $this->inventory->updateMarketplace($item, true, 15);
        $this->assertSame(InventoryItem::MARKET_ACTIVE, $item->marketplace_status);
    }

    public function test_cannot_sell_more_than_available_or_stock_reserved_for_orders(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 10);
        $item->update(['reserved_quantity' => 4]);

        try {
            $this->inventory->stockOut($item, 7);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('6 bag available', $e->errors()['quantity'][0]);
            $this->assertStringContainsString('4 reserved', $e->errors()['quantity'][0]);
        }

        $this->assertEquals(10, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_OUT)->count());
    }

    public function test_adjustment_sets_counted_quantity_records_signed_delta_and_requires_reason(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 50);
        $this->inventory->updateMarketplace($item, true, 40);

        $movement = $this->inventory->adjustTo($item, 35, 'Stock count');

        $this->assertEquals(35, $item->quantity_on_hand);
        $this->assertEquals(-15, $movement->quantity);
        $this->assertSame('Stock count', $movement->reason);
        $this->assertEquals(35, $item->marketplace_quantity);

        $this->expectException(ValidationException::class);
        $this->inventory->adjustTo($item, 30, '  ');
    }

    public function test_marketplace_quantity_cannot_exceed_stock(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 10);

        $this->expectException(ValidationException::class);
        $this->inventory->updateMarketplace($item, true, 11);
    }

    public function test_hiding_or_deactivating_keeps_physical_stock(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 10);
        $this->inventory->updateMarketplace($item, true, 5);

        $this->inventory->updateMarketplace($item, false, 5, active: false);

        $this->assertEquals(10, $item->quantity_on_hand);
        $this->assertFalse($item->is_marketplace_visible);
        $this->assertSame(InventoryItem::MARKET_INACTIVE, $item->marketplace_status);
        $this->assertSame(0, InventoryItem::onMarketplace()->count());
    }

    public function test_only_approved_sellers_with_located_stores_and_approved_products_can_list(): void
    {
        $item = $this->createItem();
        $this->inventory->stockIn($item, 10);

        $this->merchant->update(['kyc_status' => 'pending']);
        $this->assertListingRefused($item, 'not approved for the marketplace');

        $this->merchant->update(['kyc_status' => 'approved']);
        $this->store->update(['latitude' => null, 'longitude' => null]);
        $this->assertListingRefused($item, 'store location');

        $this->store->update(['latitude' => -6.77, 'longitude' => 39.229]);
        $item->update(['product_id' => null]);
        $this->assertListingRefused($item, 'catalog product');

        $item->update(['product_id' => \App\Models\Product::first()->id]);
        $this->inventory->updateMarketplace($item, true, 5);
        $this->assertSame(1, InventoryItem::onMarketplace()->count());

        // Suspending the seller takes the listing off the marketplace immediately.
        $this->merchant->update(['status' => 'suspended']);
        $this->assertSame(0, InventoryItem::onMarketplace()->count());
    }

    private function assertListingRefused(InventoryItem $item, string $reason): void
    {
        try {
            $this->inventory->updateMarketplace($item, true, 5);
            $this->fail('Expected listing to be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($reason, $e->errors()['is_marketplace_visible'][0]);
        }
    }
}
