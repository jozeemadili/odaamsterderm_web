<?php

namespace App\Livewire\Admin\Marketplace;

use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Services\MarketplaceMonitor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Marketplace health and every listing across sellers. */
#[Layout('layouts.admin', ['title' => 'Marketplace'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $seller = '';

    #[Url]
    public string $show = 'live'; // live, all, out_of_stock, hidden

    public function mount(): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace'), 403);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'seller', 'show'], true)) {
            $this->resetPage();
        }
    }

    public function render(MarketplaceMonitor $monitor)
    {
        $listings = InventoryItem::query()
            ->with(['merchant', 'branch', 'product.animalType'])
            ->whereNotNull('product_id')
            ->when($this->show === 'live', fn ($q) => $q->onMarketplace())
            ->when($this->show === 'out_of_stock', fn ($q) => $q->where('is_marketplace_visible', true)->where('marketplace_status', InventoryItem::MARKET_OUT_OF_STOCK))
            ->when($this->show === 'hidden', fn ($q) => $q->where('is_marketplace_visible', false))
            ->when($this->seller, fn ($q) => $q->where('inventory_items.merchant_id', $this->seller))
            ->when($this->search, fn ($q) => $q->where('inventory_items.name', 'like', "%{$this->search}%"))
            ->orderBy('inventory_items.name')
            ->paginate(25);

        return view('livewire.admin.marketplace.index', [
            'overview' => $monitor->overview(),
            'listings' => $listings,
            'sellers' => Merchant::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }
}
