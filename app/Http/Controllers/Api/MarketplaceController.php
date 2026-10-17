<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\MarketProductResource;
use App\Http\Resources\Market\OfferResource;
use App\Http\Resources\Market\PublicStoreResource;
use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\MarketplaceSearch;
use App\Support\GeoPoint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public marketplace discovery — no sign-in needed. Pass lat/lng (device
 * GPS or a point the customer picked) to rank and show distances.
 */
class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceSearch $search) {}

    /** Everything the search filters need, in one call. */
    public function filters()
    {
        $tree = fn ($nodes) => $nodes->map(fn ($n) => [
            'id' => $n->id,
            'name' => $n->name,
            'children' => $n->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ])->values();

        return response()->json([
            'animal_types' => $tree(AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get()),
            'categories' => $tree(ProductCategory::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get()),
            'brands' => $this->search->brands(),
            'sellers' => $this->search->sellers()->map(fn ($m) => ['id' => $m->id, 'name' => $m->business_name])->values(),
            'sorts' => collect(MarketplaceSearch::SORTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    public function products(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'animal_type_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:255'],
            'merchant_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'radius_km' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'sort' => ['nullable', Rule::in(array_keys(MarketplaceSearch::SORTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ] + $this->locationRules());

        $results = $this->search->products(
            [...$data, 'in_stock' => $request->boolean('in_stock')],
            $this->point($request),
            $data['sort'] ?? MarketplaceSearch::SORT_NEAREST,
            $data['per_page'] ?? 20,
        );

        return MarketProductResource::collection($results);
    }

    /** One product and every seller offering it — the comparison table. */
    public function product(Request $request, Product $product)
    {
        abort_unless($product->isApproved(), 404);

        $data = $request->validate([
            'sort' => ['nullable', Rule::in([MarketplaceSearch::SORT_NEAREST, MarketplaceSearch::SORT_PRICE, MarketplaceSearch::SORT_AVAILABILITY])],
        ] + $this->locationRules());

        $product->load(['animalType', 'category.parent', 'media']);
        $offers = $this->search->offersFor($product, $this->point($request), $data['sort'] ?? MarketplaceSearch::SORT_NEAREST);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'display_name' => $product->displayName(),
                'brand' => $product->brand,
                'pack_size' => $product->pack_size,
                'unit' => $product->unit,
                'description' => $product->description,
                'image_url' => $product->image()?->getUrl(),
                'animal_type' => $product->animalType?->name,
                'category' => $product->category?->fullName(),
            ],
            'offers' => OfferResource::collection($offers),
        ]);
    }

    public function stores(Request $request)
    {
        $data = $request->validate([
            'radius_km' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ] + $this->locationRules());

        return PublicStoreResource::collection(
            $this->search->stores($this->point($request), $data['limit'] ?? 20, isset($data['radius_km']) ? (float) $data['radius_km'] : null)
        );
    }

    public function store(Request $request, Branch $store)
    {
        $store->load('merchant');
        abort_unless($this->search->isPublicStore($store), 404);

        $request->validate($this->locationRules());

        if ($point = $this->point($request)) {
            $store->distance_km = $point->distanceTo($store->latitude, $store->longitude);
        }

        return response()->json([
            'store' => new PublicStoreResource($store),
            'offers' => OfferResource::collection($this->search->offersInStore($store)),
        ]);
    }

    protected function locationRules(): array
    {
        return [
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }

    protected function point(Request $request): ?GeoPoint
    {
        return GeoPoint::tryFrom($request->input('lat'), $request->input('lng'));
    }
}
