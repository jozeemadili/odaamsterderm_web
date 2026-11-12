<?php

namespace Tests\Feature\Marketplace;

use App\Models\InventoryItem;
use App\Models\Producer;
use App\Services\MarketplaceSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceProducersTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_only_the_chosen_producers_products_are_shown_to_customers(): void
    {
        $this->buildMarketplace();
        $amsterdam = Producer::create(['code' => 'BR-AMS', 'name' => 'Amsterdam Group', 'slug' => 'amsterdam-group']);
        $other = Producer::create(['code' => 'BR-OTH', 'name' => 'Other Feeds', 'slug' => 'other-feeds']);
        $starter = $this->chickenStarter();
        $starter->update(['producer_id' => $other->id]);

        $this->artisan('catalog:marketplace-producers', ['codes' => ['BR-AMS']])->assertSuccessful();

        $this->assertTrue($amsterdam->fresh()->on_marketplace);
        $this->assertFalse($other->fresh()->on_marketplace);

        $names = app(MarketplaceSearch::class)->products()->getCollection()->pluck('name');
        $this->assertNotContains('Chicken Starter', $names);
        $this->assertContains('Layers Mash', $names); // no producer: still shown

        $this->get(route('market.product', $starter->slug))->assertNotFound();
        $this->getJson("/api/v1/market/products/{$starter->id}")->assertNotFound();
        $this->assertSame(0, InventoryItem::onMarketplace()->where('product_id', $starter->id)->count());
        $this->assertContains('Products of Other Feeds are not shown on the marketplace.', InventoryItem::where('product_id', $starter->id)->first()->marketplaceBlockers());

        // Unknown codes change nothing.
        $this->artisan('catalog:marketplace-producers', ['codes' => ['BR-NOPE']])->assertFailed();
        $this->assertTrue($amsterdam->fresh()->on_marketplace);
    }
}
