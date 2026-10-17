<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function categories(Request $request)
    {
        $categories = ProductCategory::active()->whereNull('parent_id')
            ->forAnimalType($request->filled('animal_type_id') ? $request->integer('animal_type_id') : null)
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
        $animalIds = $request->filled('animal_type_id') ? AnimalType::find($request->integer('animal_type_id'))?->selfAndChildIds() : null;
        $categoryIds = $request->filled('category_id') ? ProductCategory::find($request->integer('category_id'))?->selfAndChildIds() : null;

        $products = Product::usableBy($merchantId)
            ->with(['animalType', 'category.parent', 'media'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('brand', 'like', '%'.$request->string('search').'%')))
            ->when($animalIds, fn ($q) => $q->whereIn('animal_type_id', $animalIds))
            ->when($categoryIds, fn ($q) => $q->whereIn('product_category_id', $categoryIds))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    /** The seller's own pending and rejected proposals. */
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
            'brand' => ['nullable', 'string', 'max:255'],
            'animal_type_id' => ['required', Rule::exists('animal_types', 'id')->where('is_active', true)],
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $category = ProductCategory::find($data['product_category_id']);
        $allowed = ProductCategory::forAnimalType((int) $data['animal_type_id'])->pluck('id');

        if (! $allowed->contains($category->parent_id ?? $category->id)) {
            throw ValidationException::withMessages(['product_category_id' => 'This category is not used for the chosen animal type.']);
        }

        $product = Product::create($data + [
            'slug' => Product::uniqueSlug($data['name'].' '.($data['pack_size'] ?? '')),
            'status' => Product::STATUS_PENDING,
            'proposed_by_merchant_id' => Auth::user()->merchant_id,
        ]);

        return (new ProductResource($product->load(['animalType', 'category.parent'])))->response()->setStatusCode(201);
    }
}
