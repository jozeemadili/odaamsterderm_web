<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\AddsToCart;
use App\Models\Branch;
use App\Services\MarketplaceSearch;
use App\Support\MarketLocation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/** Public store page: location, pickup point, hours and what it sells online. */
#[Layout('layouts.market')]
class StoreShow extends Component
{
    use AddsToCart;

    public Branch $store;

    public function mount(Branch $store, MarketplaceSearch $search): void
    {
        $store->load('merchant');
        abort_unless($search->isPublicStore($store), 404);
        $this->store = $store;
    }

    #[On('market-location-updated')]
    public function locationUpdated(): void {}

    public function render(MarketplaceSearch $search)
    {
        $from = MarketLocation::current();

        return view('livewire.market.store-show', [
            'from' => $from,
            'distance' => $from?->distanceTo($this->store->latitude, $this->store->longitude),
            'offers' => $search->offersInStore($this->store),
        ])->title($this->store->name);
    }
}
