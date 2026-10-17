<?php

namespace Tests\Feature\Market;

use App\Models\AnimalType;
use App\Models\InventoryItem;
use App\Services\MarketplaceSearch;
use App\Support\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class MarketplaceSearchTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private MarketplaceSearch $search;

    private GeoPoint $sinza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->search = app(MarketplaceSearch::class);
        $this->sinza = new GeoPoint($this->customerLat, $this->customerLng);
    }

    public function test_offers_are_ranked_by_distance_and_include_out_of_stock_sellers(): void
    {
        $offers = $this->search->offersFor($this->chickenStarter(), $this->sinza);

        $this->assertSame(['Seller A', 'Seller B', 'Seller C'], $offers->map->merchant->pluck('business_name')->all());
        $this->assertEqualsWithDelta(1.2, $offers[0]->distance_km, 0.1);
        $this->assertEqualsWithDelta(4.4, $offers[1]->distance_km, 0.2);
        $this->assertEqualsWithDelta(7.6, $offers[2]->distance_km, 0.2);
        $this->assertSame([1, 1, 0], $offers->map(fn ($o) => (int) $o->is_available)->all());
    }

    public function test_lowest_price_and_highest_availability_put_available_offers_first(): void
    {
        $byPrice = $this->search->offersFor($this->chickenStarter(), $this->sinza, MarketplaceSearch::SORT_PRICE);
        $this->assertSame(['Seller B', 'Seller A', 'Seller C'], $byPrice->map->merchant->pluck('business_name')->all());

        $byStock = $this->search->offersFor($this->chickenStarter(), $this->sinza, MarketplaceSearch::SORT_AVAILABILITY);
        $this->assertSame(['Seller A', 'Seller B', 'Seller C'], $byStock->map->merchant->pluck('business_name')->all());
    }

    public function test_nearest_falls_back_to_price_without_a_location(): void
    {
        $offers = $this->search->offersFor($this->chickenStarter(), null);

        $this->assertSame('Seller B', $offers->first()->merchant->business_name);
        $this->assertNull($offers->first()->distance_km);
    }

    public function test_hidden_unapproved_suspended_and_unlocated_offers_never_show(): void
    {
        $this->seller('Pending Seller', -6.78, 39.23, [['Chicken Starter', 10, 10, 1000]], approved: false);

        InventoryItem::where('branch_id', $this->stores['B']->id)->update(['is_marketplace_visible' => false]);
        $this->stores['C']->merchant->update(['status' => 'suspended']);

        $names = $this->search->offersFor($this->chickenStarter(), $this->sinza)->map->merchant->pluck('business_name')->all();
        $this->assertSame(['Seller A'], $names);

        $this->stores['A']->update(['latitude' => null]);
        $this->assertCount(0, $this->search->offersFor($this->chickenStarter(), $this->sinza));
    }

    public function test_product_results_aggregate_price_sellers_availability_and_nearest_store(): void
    {
        $results = $this->search->products([], $this->sinza);
        $starter = $results->getCollection()->firstWhere('name', 'Chicken Starter');

        $this->assertEquals(43500, $starter->min_price);
        $this->assertEquals(3, $starter->sellers_count);
        $this->assertEquals(55, $starter->available_quantity);
        $this->assertEqualsWithDelta(1.2, $starter->nearest_km, 0.1);
        $this->assertSame(3, $results->total());
    }

    public function test_filters_by_animal_group_search_price_and_availability(): void
    {
        $poultry = AnimalType::where('slug', 'poultry')->first();
        $names = fn ($filters, $sort = MarketplaceSearch::SORT_NAME) => $this->search->products($filters, $this->sinza, $sort)->getCollection()->pluck('name')->all();

        // "Poultry" includes products tagged with its child type "Chicken".
        $this->assertSame(['Chicken Starter', 'Layers Mash'], $names(['animal_type_id' => $poultry->id]));
        $this->assertSame(['Tilapia Floating Feed'], $names(['search' => 'tilapia']));
        $this->assertSame(['Chicken Starter'], $names(['max_price' => 50000]));
        $this->assertSame(['Layers Mash'], $names(['min_price' => 55000, 'animal_type_id' => $poultry->id]));
        $this->assertSame(['Chicken Starter', 'Layers Mash'], $names(['merchant_id' => $this->stores['A']->merchant_id]));
    }

    public function test_radius_limits_results_to_nearby_stores(): void
    {
        $names = $this->search->products(['radius_km' => 2], $this->sinza)->getCollection()->pluck('name')->sort()->values()->all();

        $this->assertSame(['Chicken Starter', 'Layers Mash'], $names);
    }

    public function test_out_of_stock_everywhere_products_sort_last_and_can_be_hidden(): void
    {
        InventoryItem::where('branch_id', $this->stores['C']->id)->update(['marketplace_status' => 'out_of_stock', 'marketplace_quantity' => 0]);

        $all = $this->search->products([], $this->sinza, MarketplaceSearch::SORT_PRICE)->getCollection();
        $this->assertSame('Tilapia Floating Feed', $all->last()->name);
        $this->assertNull($all->last()->min_price);

        $inStock = $this->search->products(['in_stock' => true], $this->sinza)->getCollection()->pluck('name');
        $this->assertNotContains('Tilapia Floating Feed', $inStock);
    }

    public function test_stores_are_listed_nearest_first_with_available_offer_counts(): void
    {
        $stores = $this->search->stores($this->sinza);

        $this->assertSame(['Seller A Store', 'Seller B Store', 'Seller C Store'], $stores->pluck('name')->all());
        $this->assertSame([2, 1, 1], $stores->pluck('offers_count')->map(fn ($c) => (int) $c)->all());
    }

    public function test_distance_formatting(): void
    {
        $this->assertSame('450 m', GeoPoint::formatKm(0.452));
        $this->assertSame('1.2 km', GeoPoint::formatKm(1.24));
        $this->assertSame('12 km', GeoPoint::formatKm(12.4));
        $this->assertNull(GeoPoint::formatKm(null));
    }
}
