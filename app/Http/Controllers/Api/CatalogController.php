<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AnimalType;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

    public function propose(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pack_size' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'producer_id' => ['nullable', Rule::exists('producers', 'id')->where('is_active', true)],
            'brand' => ['nullable', 'string', 'max:255'],
            'animal_type_id' => ['required', Rule::exists('animal_types', 'id')->where('is_active', true)],
            'animal_type_ids' => ['nullable', 'array'],
            'animal_type_ids.*' => [Rule::exists('animal_types', 'id')->where('is_active', true)],
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! empty($data['producer_id'])) {
            $data['brand'] = Producer::whereKey($data['producer_id'])->value('name');
        }

        $otherAnimals = array_map('intval', Arr::pull($data, 'animal_type_ids') ?? []);

        $product = Product::create($data + [
            'slug' => Product::uniqueSlug($data['name'].' '.($data['pack_size'] ?? '').' '.($data['brand'] ?? '')),
            'status' => Product::STATUS_PENDING,
            'proposed_by_merchant_id' => Auth::user()->merchant_id,
        ]);

        $product->animalTypes()->syncWithoutDetaching($otherAnimals);
        $product->syncDefaultVariant();

        return (new ProductResource($product->load(['animalType', 'animalTypes', 'category.parent'])))->response()->setStatusCode(201);
    }
}
