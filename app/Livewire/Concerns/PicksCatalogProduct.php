<?php

namespace App\Livewire\Concerns;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;

/**
 * Type-ahead picker for linking an inventory item to a catalog product.
 * Sellers see approved products plus their own pending proposals.
 */
trait PicksCatalogProduct
{
    public string $catalogSearch = '';

    /** @var array<int, array{id:int,label:string,unit:string,status:string,animal:?string}> */
    public array $catalogMatches = [];

    public ?int $product_id = null;

    public string $productLabel = '';

    public function updatedCatalogSearch(string $value): void
    {
        $term = trim($value);

        if (mb_strlen($term) < 2) {
            $this->catalogMatches = [];

            return;
        }

        $this->catalogMatches = Product::usableBy(Auth::user()->merchant_id)
            ->with('animalType')
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => $p->displayName().($p->brand ? " — {$p->brand}" : ''),
                'unit' => $p->unit,
                'status' => $p->status,
                'animal' => $p->animalType?->name,
            ])
            ->all();
    }

    public function selectCatalogProduct(int $productId): void
    {
        $product = Product::usableBy(Auth::user()->merchant_id)->findOrFail($productId);

        $this->product_id = $product->id;
        $this->productLabel = $product->displayName().($product->isPending() ? ' (pending approval)' : '');
        $this->catalogSearch = '';
        $this->catalogMatches = [];

        $this->catalogProductSelected($product);
    }

    public function clearCatalogProduct(): void
    {
        $this->product_id = null;
        $this->productLabel = '';
    }

    /** Hook for the component to pre-fill fields from the chosen product. */
    protected function catalogProductSelected(Product $product): void {}

    protected function catalogProductRules(): array
    {
        return ['product_id' => ['nullable', function ($attribute, $value, $fail) {
            if ($value && ! Product::usableBy(Auth::user()->merchant_id)->whereKey($value)->exists()) {
                $fail('Choose a product from the catalog.');
            }
        }]];
    }
}
