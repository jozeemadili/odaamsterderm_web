<?php

namespace App\Livewire\Portal\Marketplace;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seller's marketplace control panel: what is exposed online, how much,
 * and why an item can't be listed yet. Physical stock is shown alongside
 * but only changes through inventory/sales.
 */
#[Layout('layouts.portal', ['title' => 'Marketplace'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $storeFilter = '';

    #[Url]
    public string $show = 'all'; // all, listed, hidden, out_of_stock

    public ?int $editingId = null;

    public bool $visible = false;

    public string $quantity = '0';

    public bool $active = true;

    public function updating(string $name): void
    {
        if (in_array($name, ['storeFilter', 'show'], true)) {
            $this->resetPage();
        }
    }

    public function edit(int $itemId): void
    {
        $item = $this->findItem($itemId);

        $this->editingId = $item->id;
        $this->visible = $item->is_marketplace_visible || $item->marketplaceBlockers() === [];
        $this->quantity = rtrim(rtrim((string) $item->marketplace_quantity, '0'), '.') ?: '0';
        $this->active = $item->marketplace_status !== InventoryItem::MARKET_INACTIVE;
        $this->resetErrorBag();
    }

    public function exposeAll(): void
    {
        $this->quantity = rtrim(rtrim(number_format($this->findItem($this->editingId)->availableQuantity(), 2, '.', ''), '0'), '.') ?: '0';
    }

    public function save(InventoryService $inventory): void
    {
        $this->validate(['quantity' => ['required', 'numeric', 'min:0']]);

        try {
            $inventory->updateMarketplace($this->findItem($this->editingId), $this->visible, (float) $this->quantity, $this->active);
        } catch (ValidationException $e) {
            $this->addError('quantity', collect($e->errors())->flatten()->first());

            return;
        }

        $this->editingId = null;
        session()->flash('status', 'Marketplace settings saved.');
    }

    public function hide(int $itemId, InventoryService $inventory): void
    {
        $item = $this->findItem($itemId);
        $inventory->updateMarketplace($item, false, (float) $item->marketplace_quantity, $item->marketplace_status !== InventoryItem::MARKET_INACTIVE);
        session()->flash('status', "{$item->name} is now hidden from the marketplace.");
    }

    protected function findItem(?int $id): InventoryItem
    {
        return InventoryItem::where('merchant_id', Auth::user()->merchant_id)
            ->with(['merchant', 'product', 'branch'])
            ->findOrFail($id);
    }

    public function render()
    {
        $merchant = Auth::user()->merchant;
        $base = InventoryItem::where('merchant_id', $merchant->id)
            ->when($this->storeFilter, fn ($q) => $q->where('branch_id', $this->storeFilter));

        $items = (clone $base)
            ->with(['product.animalType', 'branch', 'merchant'])
            ->when($this->show === 'listed', fn ($q) => $q->onMarketplace())
            ->when($this->show === 'hidden', fn ($q) => $q->where('is_marketplace_visible', false))
            ->when($this->show === 'out_of_stock', fn ($q) => $q->where('is_marketplace_visible', true)->where('marketplace_status', InventoryItem::MARKET_OUT_OF_STOCK))
            ->orderByDesc('is_marketplace_visible')
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.portal.marketplace.index', [
            'merchant' => $merchant,
            'items' => $items,
            'stores' => Branch::where('merchant_id', $merchant->id)->orderBy('name')->get(),
            'stats' => [
                'listed' => (clone $base)->onMarketplace()->count(),
                'units' => (float) (clone $base)->onMarketplace()->sum('marketplace_quantity'),
                'out_of_stock' => (clone $base)->where('is_marketplace_visible', true)->where('marketplace_status', InventoryItem::MARKET_OUT_OF_STOCK)->count(),
                'unlinked' => (clone $base)->whereNull('product_id')->count(),
            ],
            'storesWithoutLocation' => Branch::where('merchant_id', $merchant->id)->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->get(),
        ]);
    }
}
