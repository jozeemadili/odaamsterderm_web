<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\GeoPoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer-facing discovery: which products are on the marketplace, which
 * sellers offer them, at what price, how many units, and how far away.
 *
 * An "offer" is an InventoryItem that the public can see: visible, active or
 * out of stock (customers still see "Out of stock" rows when comparing),
 * linked to an approved catalog product, in an active store with a map
 * location, owned by an approved seller. It is "available" when it is
 * active with marketplace quantity left.
 */
class MarketplaceSearch
{
    public const SORT_NEAREST = 'nearest';

    public const SORT_PRICE = 'price';

    public const SORT_AVAILABILITY = 'availability';

    public const SORT_NAME = 'name';

    /** Order of filters['product_ids'] (AI search ranking); not offered in the sort menu. */
    public const SORT_RELEVANCE = 'relevance';

    public const SORTS = [
        self::SORT_NEAREST => 'Nearest',
        self::SORT_PRICE => 'Lowest price',
        self::SORT_AVAILABILITY => 'Highest availability',
        self::SORT_NAME => 'Name',
    ];

    // Bound numbers are compared as "(? + 0)": PDO sends floats as strings,
    // and SQLite would otherwise compare a number against text.
    private const AVAILABLE_SQL = "(inventory_items.marketplace_status = 'active' AND inventory_items.marketplace_quantity > 0)";

    /**
     * Products with at least one public offer, with per-product price,
     * seller count, available units and nearest available store.
     *
     * @param  array{search?:?string, animal_type_id?:int|string|null, category_id?:int|string|null, brand?:?string, merchant_id?:int|string|null, min_price?:mixed, max_price?:mixed, in_stock?:bool, radius_km?:mixed, product_ids?:list<int>, any_words?:list<string>}  $filters
     */
    public function products(array $filters = [], ?GeoPoint $from = null, string $sort = self::SORT_NEAREST, int $perPage = 20): LengthAwarePaginator
    {
        $available = self::AVAILABLE_SQL;

        $query = Product::query()
            ->select('products.*')
            ->join('inventory_items', 'inventory_items.product_id', '=', 'products.id')
            ->tap(fn (Builder $q) => $this->onlyPublicOffers($q))
            ->where('products.status', Product::STATUS_APPROVED)
            ->selectRaw("MIN(CASE WHEN {$available} THEN inventory_items.unit_price END) as min_price")
            ->selectRaw("MAX(CASE WHEN {$available} THEN inventory_items.unit_price END) as max_price")
            ->selectRaw('COUNT(DISTINCT inventory_items.merchant_id) as sellers_count')
            ->selectRaw("SUM(CASE WHEN {$available} THEN inventory_items.marketplace_quantity ELSE 0 END) as available_quantity")
            ->groupBy('products.id');

        if ($from) {
            [$distance, $bindings] = $from->distanceSql('branches.latitude', 'branches.longitude');
            $query->selectRaw("MIN(CASE WHEN {$available} THEN {$distance} END) as nearest_km", $bindings);

            if (is_numeric($filters['radius_km'] ?? null) && $filters['radius_km'] > 0) {
                $query->whereRaw("{$distance} <= (? + 0)", [...$bindings, (float) $filters['radius_km']]);
            }
        } else {
            $query->selectRaw('NULL as nearest_km');
        }

        $this->applyProductFilters($query, $filters);

        if (! empty($filters['in_stock'])) {
            $query->havingRaw("SUM(CASE WHEN {$available} THEN inventory_items.marketplace_quantity ELSE 0 END) > 0");
        }
        if (is_numeric($filters['min_price'] ?? null)) {
            $query->havingRaw("MIN(CASE WHEN {$available} THEN inventory_items.unit_price END) >= (? + 0)", [(float) $filters['min_price']]);
        }
        if (is_numeric($filters['max_price'] ?? null)) {
            $query->havingRaw("MIN(CASE WHEN {$available} THEN inventory_items.unit_price END) <= (? + 0)", [(float) $filters['max_price']]);
        }

        // Sort in an outer query: MySQL 5.7 can't use aggregate aliases inside
        // ORDER BY expressions (needed for "nulls last").
        $results = Product::query()
            ->fromSub($query->toBase(), 'products')
            ->with(['animalType', 'category.parent', 'media']);

        $ids = array_values(array_map('intval', $filters['product_ids'] ?? []));

        match ($sort === self::SORT_NEAREST && ! $from ? self::SORT_PRICE : $sort) {
            self::SORT_RELEVANCE => $ids
                ? $results->orderByRaw('CASE id '.collect($ids)->map(fn (int $id, int $i) => "WHEN {$id} THEN {$i}")->implode(' ').' ELSE '.count($ids).' END')
                : $results->orderBy('name'),
            self::SORT_NEAREST => $results->orderByRaw('nearest_km IS NULL, nearest_km'),
            self::SORT_PRICE => $results->orderByRaw('min_price IS NULL, min_price'),
            self::SORT_AVAILABILITY => $results->orderByDesc('available_quantity'),
            default => $results->orderBy('name'),
        };

        return $results->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * Every public offer of one product — the seller comparison table.
     *
     * @return Collection<int, InventoryItem> each with distance_km and is_available
     */
    public function offersFor(Product $product, ?GeoPoint $from = null, string $sort = self::SORT_NEAREST): Collection
    {
        $query = $this->offers($from)->where('inventory_items.product_id', $product->id);

        $sort = $sort === self::SORT_NEAREST && ! $from ? self::SORT_PRICE : $sort;

        // Available offers first for price/availability; nearest keeps pure distance order.
        match ($sort) {
            self::SORT_NEAREST => $query->orderByRaw('distance_km'),
            self::SORT_PRICE => $query->orderByRaw('is_available DESC, inventory_items.unit_price'),
            default => $query->orderByRaw('is_available DESC, inventory_items.marketplace_quantity DESC'),
        };

        return $query->orderBy('inventory_items.id')->get();
    }

    /**
     * Public offers in one store.
     *
     * @return Collection<int, InventoryItem>
     */
    public function offersInStore(Branch $store): Collection
    {
        return $this->offers()
            ->where('inventory_items.branch_id', $store->id)
            ->orderByRaw('is_available DESC')
            ->orderBy('products.name')
            ->get();
    }

    /**
     * Stores customers can buy from, nearest first when a location is known.
     *
     * @return Collection<int, Branch> each with distance_km and offers_count
     */
    public function stores(?GeoPoint $from = null, int $limit = 12, ?float $radiusKm = null): Collection
    {
        $available = self::AVAILABLE_SQL;

        $query = Branch::query()
            ->select('branches.*')
            ->join('merchants', 'merchants.id', '=', 'branches.merchant_id')
            ->tap(fn (Builder $q) => $this->onlyActiveSellersAndStores($q))
            ->with('merchant')
            ->withCount(['inventoryItems as offers_count' => fn (Builder $q) => $q
                ->where('is_marketplace_visible', true)
                ->whereRaw($available)
                ->whereHas('product', fn (Builder $p) => $p->where('status', Product::STATUS_APPROVED))]);

        if ($from) {
            [$distance, $bindings] = $from->distanceSql('branches.latitude', 'branches.longitude');
            $query->selectRaw("{$distance} as distance_km", $bindings)->orderBy('distance_km');

            if ($radiusKm) {
                $query->whereRaw("{$distance} <= (? + 0)", [...$bindings, $radiusKm]);
            }
        } else {
            $query->selectRaw('NULL as distance_km')->orderBy('branches.name');
        }

        return $query->limit($limit)->get()->filter(fn (Branch $b) => $b->offers_count > 0)->values();
    }

    /** Whether a store page may be shown to the public. */
    public function isPublicStore(Branch $store): bool
    {
        return $store->hasLocation()
            && $store->isActive()
            && $store->is_marketplace_enabled
            && $store->merchant?->canSellOnMarketplace();
    }

    /** Distinct brands of products currently on the marketplace (for filters). */
    public function brands(): array
    {
        return Product::query()
            ->join('inventory_items', 'inventory_items.product_id', '=', 'products.id')
            ->tap(fn (Builder $q) => $this->onlyPublicOffers($q))
            ->where('products.status', Product::STATUS_APPROVED)
            ->whereNotNull('products.brand')
            ->distinct()
            ->orderBy('products.brand')
            ->pluck('products.brand')
            ->all();
    }

    /** Sellers with at least one public offer (for the seller filter). */
    public function sellers(): Collection
    {
        return Merchant::query()
            ->marketplaceApproved()
            ->whereHas('inventoryItems', fn (Builder $q) => $q->onMarketplace())
            ->orderBy('business_name')
            ->get(['id', 'business_name']);
    }

    /** Base query of public offers with store, seller and product joined. */
    protected function offers(?GeoPoint $from = null): Builder
    {
        $query = InventoryItem::query()
            ->select('inventory_items.*')
            ->selectRaw(self::AVAILABLE_SQL.' as is_available')
            ->join('products', 'products.id', '=', 'inventory_items.product_id')
            ->tap(fn (Builder $q) => $this->onlyPublicOffers($q))
            ->where('products.status', Product::STATUS_APPROVED)
            ->with(['branch', 'merchant', 'product']);

        if ($from) {
            [$distance, $bindings] = $from->distanceSql('branches.latitude', 'branches.longitude');
            $query->selectRaw("{$distance} as distance_km", $bindings);
        } else {
            $query->selectRaw('NULL as distance_km');
        }

        return $query;
    }

    /** Joins branches/merchants and keeps only rows the public may see. Expects inventory_items in the query. */
    protected function onlyPublicOffers(Builder $query): void
    {
        $query
            ->join('branches', 'branches.id', '=', 'inventory_items.branch_id')
            ->join('merchants', 'merchants.id', '=', 'inventory_items.merchant_id')
            ->where('inventory_items.is_marketplace_visible', true)
            ->whereIn('inventory_items.marketplace_status', [InventoryItem::MARKET_ACTIVE, InventoryItem::MARKET_OUT_OF_STOCK]);

        $this->onlyActiveSellersAndStores($query);
    }

    protected function onlyActiveSellersAndStores(Builder $query): void
    {
        $query
            ->where('branches.status', Branch::STATUS_ACTIVE)
            ->where('branches.is_marketplace_enabled', true)
            ->whereNotNull('branches.latitude')
            ->whereNotNull('branches.longitude')
            ->where('merchants.kyc_status', Merchant::KYC_APPROVED)
            ->where('merchants.status', Merchant::STATUS_ACTIVE);
    }

    protected function applyProductFilters(Builder $query, array $filters): void
    {
        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn (Builder $q) => $q
                ->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.brand', 'like', "%{$term}%")
                ->orWhere('products.pack_size', 'like', "%{$term}%"));
        }

        // Any of these words (e.g. a sentence when Ask AI is unavailable).
        if ($words = array_filter($filters['any_words'] ?? [])) {
            $query->where(function (Builder $q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('products.name', 'like', "%{$word}%")->orWhere('products.brand', 'like', "%{$word}%");
                }
            });
        }

        if (array_key_exists('product_ids', $filters)) {
            $query->whereIn('products.id', array_map('intval', $filters['product_ids']));
        }

        if ($animal = AnimalType::find($filters['animal_type_id'] ?? null)) {
            $query->whereIn('products.animal_type_id', $animal->selfAndChildIds());
        }

        if ($category = ProductCategory::find($filters['category_id'] ?? null)) {
            $query->whereIn('products.product_category_id', $category->selfAndChildIds());
        }

        if (! empty($filters['brand'])) {
            $query->where('products.brand', $filters['brand']);
        }

        if (! empty($filters['merchant_id'])) {
            $query->where('inventory_items.merchant_id', (int) $filters['merchant_id']);
        }
    }
}
