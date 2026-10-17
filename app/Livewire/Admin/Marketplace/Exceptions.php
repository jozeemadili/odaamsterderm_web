<?php

namespace App\Livewire\Admin\Marketplace;

use App\Models\InventoryItem;
use App\Services\InventoryService;
use App\Services\MarketplaceMonitor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Inventory and marketplace exceptions that need attention. */
#[Layout('layouts.admin', ['title' => 'Marketplace Exceptions'])]
class Exceptions extends Component
{
    public bool $showInfo = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace'), 403);
    }

    /** Takes a problem listing off the marketplace; physical stock is untouched. */
    public function hideListing(int $itemId, InventoryService $inventory): void
    {
        abort_unless(Auth::user()->can('manage-merchants'), 403);

        $item = InventoryItem::findOrFail($itemId);
        $inventory->updateMarketplace(
            $item,
            false,
            min((float) $item->marketplace_quantity, $item->availableQuantity()),
            false,
            ['reference' => 'admin_hidden'],
        );

        session()->flash('status', "{$item->name} is hidden from the marketplace. The seller can relist it once the stock is corrected.");
    }

    public function render(MarketplaceMonitor $monitor)
    {
        $checks = collect($monitor->exceptions())
            ->when(! $this->showInfo, fn ($c) => $c->where('severity', '!=', 'info'));

        return view('livewire.admin.marketplace.exceptions', [
            'checks' => $checks,
            'canHide' => Auth::user()->can('manage-merchants'),
        ]);
    }
}
