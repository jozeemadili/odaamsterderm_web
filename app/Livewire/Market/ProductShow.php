<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\AddsToCart;
use App\Models\Product;
use App\Services\MarketplaceSearch;
use App\Support\MarketLocation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** One product, every seller offering it: distance, price, availability. */
#[Layout('layouts.market')]
class ProductShow extends Component
{
    use AddsToCart;

    public Product $product;

    #[Url]
    public string $sort = MarketplaceSearch::SORT_NEAREST;

    /** Variant id: compare one pack size at a time. */
    #[Url]
    public ?int $pack = null;

    public function mount(Product $product): void
    {
        abort_unless($product->isApproved(), 404);
        $this->product = $product->load(['animalType', 'animalTypes', 'lifeStages', 'category.parent', 'media', 'producer.media']);
    }

    #[On('market-location-updated')]
    public function locationUpdated(): void
    {
        $this->sort = MarketLocation::current() ? MarketplaceSearch::SORT_NEAREST : MarketplaceSearch::SORT_PRICE;
    }

    public function render(MarketplaceSearch $search)
    {
        $from = MarketLocation::current();

        if (! $from && $this->sort === MarketplaceSearch::SORT_NEAREST) {
            $this->sort = MarketplaceSearch::SORT_PRICE;
        }
        $allOffers = $search->offersFor($this->product, $from, $this->sort);
        $packs = $allOffers->pluck('variant')->filter()->unique('id')->sortBy('sort_order')->values();
        $offers = $this->pack ? $allOffers->where('product_variant_id', $this->pack)->values() : $allOffers;

        return view('livewire.market.product-show', [
            'from' => $from,
            'photos' => $this->product->galleryUrls($offers),
            'offers' => $offers,
            'packs' => $packs,
            'mapStores' => $offers->unique('branch_id')->map(fn ($o) => [
                'lat' => $o->branch->latitude,
                'lng' => $o->branch->longitude,
                'name' => $o->branch->name,
                'note' => $o->is_available ? 'TZS '.number_format($o->unit_price).' · '.rtrim(rtrim((string) $o->marketplace_quantity, '0'), '.').' '.$o->sellingUnit() : 'Out of stock',
                'url' => route('market.store', $o->branch_id),
            ])->values(),
        ])->title($this->product->displayName());
    }
}
