<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AnimalType;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared catalog for sellers: the animal/category trees, approved products
 * (plus the seller's own pending proposals) and product proposals.
 */
class CatalogController extends Controller
{
    public function animalTypes()
    {
        $groups = AnimalType::active()->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return response()->json(['data' => $groups->map(fn (AnimalType $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'children' => $g->children->map(fn (AnimalType $c) => ['id' => $c->id, 'name' => $c->name])->values(),
        ])]);
    }

    public function categories()
    {
        // Any category can be used with any animal; animal_type_id is still
        // accepted from older app versions but no longer narrows the list.
        $categories = ProductCategory::active()->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->active()])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return response()->json(['data' => $categories->map(fn (ProductCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'children' => $c->children->map(fn (ProductCategory $s) => ['id' => $s->id, 'name' => $s->name])->values(),
        ])]);
    }

    public function products(Request $request)
    {
        $merchantId = Auth::user()->merchant_id;
        $categoryIds = $request->filled('category_id') ? ProductCategory::find($request->integer('category_id'))?->selfAndChildIds() : null;

        $products = Product::usableBy($merchantId)
            ->with(['animalType', 'animalTypes', 'lifeStages', 'variants', 'category.parent', 'media', 'producer.media'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('brand', 'like', '%'.$request->string('search').'%')
                ->orWhere('search_keywords', 'like', '%'.$request->string('search').'%')))
            ->forAnimal($request->integer('animal_type_id') ?: null)
            ->when($categoryIds, fn ($q) => $q->whereIn('product_category_id', $categoryIds))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    /** The seller's own pending and rejected proposals. */
    /** Active producers, for the "propose a product" form. */
    public function producers()
    {
        return response()->json([
            'data' => Producer::active()->with('media')->orderBy('name')->get()
                ->map(fn (Producer $p) => ['id' => $p->id, 'name' => $p->name, 'logo_url' => $p->logoUrl(), 'country' => $p->country])
                ->values(),
        ]);
    }

    public function proposals()
    {
        return ProductResource::collection(
            Product::where('proposed_by_merchant_id', Auth::user()->merchant_id)
                ->whereIn('status', [Product::STATUS_PENDING, Product::STATUS_REJECTED])
                ->with(['animalType', 'category.parent'])
                ->latest()
                ->get()
        );
    }

    /**
     * Retired: agents no longer create catalogue products. A missing product
     * is requested with photos of its label (POST /product-requests) and the
     * data team adds it.
     */
    public function propose(Request $request)
    {
        return response()->json([
            'message' => 'Products are no longer added by hand. Send a product request with photos of the label instead.',
            'use' => '/api/v1/product-requests',
        ], 410);
    }
}
