<?php

namespace App\Livewire\Market;

use App\Models\AnimalType;
use App\Models\ProductCategory;
use App\Services\MarketplaceSearch;
use App\Support\MarketLocation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Public marketplace: search, filter and sort animal products near you. */
#[Layout('layouts.market')]
class Home extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $animal = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $brand = '';

    #[Url]
    public string $seller = '';

    #[Url]
    public string $sort = MarketplaceSearch::SORT_NEAREST;

    #[Url(as: 'in_stock')]
    public bool $inStock = false;

    #[Url(as: 'min')]
    public string $minPrice = '';

    #[Url(as: 'max')]
    public string $maxPrice = '';

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    public function chooseAnimal(?int $id): void
    {
        $this->animal = $id ? (string) $id : '';
        $this->category = '';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['animal', 'category', 'brand', 'seller', 'inStock', 'minPrice', 'maxPrice']);
        $this->resetPage();
    }

    #[On('market-location-updated')]
    public function locationUpdated(): void
    {
        // A freshly chosen location is what customers want ranked by.
        $this->sort = MarketLocation::current() ? MarketplaceSearch::SORT_NEAREST : MarketplaceSearch::SORT_PRICE;
        $this->resetPage();
    }

    public function render(MarketplaceSearch $search)
    {
        $from = MarketLocation::current();

        // "Nearest" means nothing without a location — show what's really applied.
        if (! $from && $this->sort === MarketplaceSearch::SORT_NEAREST) {
            $this->sort = MarketplaceSearch::SORT_PRICE;
        }

        $filters = [
            'search' => $this->q,
            'animal_type_id' => $this->animal,
            'category_id' => $this->category,
            'brand' => $this->brand,
            'merchant_id' => $this->seller,
            'in_stock' => $this->inStock,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
        ];

        $hasFilters = $this->q !== '' || array_filter([$this->animal, $this->category, $this->brand, $this->seller, $this->minPrice, $this->maxPrice]) || $this->inStock;

        return view('livewire.market.home', [
            'from' => $from,
            'products' => $search->products($filters, $from, $this->sort, 24),
            'stores' => $hasFilters ? collect() : $search->stores($from, 6),
            'animalGroups' => AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get(),
            'categories' => ProductCategory::active()->whereNull('parent_id')
                ->forAnimalType($this->animal ? (int) $this->animal : null)
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->get(),
            'brands' => $search->brands(),
            'sellers' => $search->sellers(),
            'hasFilters' => $hasFilters,
        ])->title($this->q !== '' ? "Search: {$this->q}" : 'Marketplace');
    }
}
