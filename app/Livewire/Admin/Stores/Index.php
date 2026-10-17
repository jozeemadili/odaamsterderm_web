<?php

namespace App\Livewire\Admin\Stores;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\StoreStatusChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** All seller stores: marketplace readiness, suspension and reactivation. */
#[Layout('layouts.admin', ['title' => 'Stores'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, live, suspended, no_location, marketplace_off

    public ?int $suspendingId = null;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace') || Auth::user()->can('manage-merchants'), 403);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'filter'], true)) {
            $this->resetPage();
        }
    }

    public function suspend(): void
    {
        abort_unless(Auth::user()->can('manage-merchants'), 403);
        $this->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Give the seller a reason.']);

        $store = Branch::findOrFail($this->suspendingId);
        $store->update(['status' => Branch::STATUS_SUSPENDED, 'suspension_reason' => $this->reason]);
        $this->notifySeller($store);

        $this->reset(['suspendingId', 'reason']);
        session()->flash('status', "{$store->name} suspended — its listings are hidden from the marketplace.");
    }

    public function reactivate(int $id): void
    {
        abort_unless(Auth::user()->can('manage-merchants'), 403);

        $store = Branch::findOrFail($id);
        $store->update(['status' => Branch::STATUS_ACTIVE, 'suspension_reason' => null]);
        $this->notifySeller($store);

        session()->flash('status', "{$store->name} reactivated.");
    }

    protected function notifySeller(Branch $store): void
    {
        Notification::send(
            User::where('merchant_id', $store->merchant_id)->where('user_type', User::TYPE_MERCHANT)->get(),
            new StoreStatusChanged($store),
        );
    }

    public function render()
    {
        $stores = Branch::query()
            ->with('merchant')
            ->withCount([
                'inventoryItems as listed_count' => fn ($q) => $q->where('is_marketplace_visible', true)->where('marketplace_status', InventoryItem::MARKET_ACTIVE),
            ])
            ->addSelect(['open_orders' => Order::selectRaw('COUNT(*)')->whereColumn('orders.branch_id', 'branches.id')->whereIn('status', Order::OPEN)])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('city', 'like', "%{$this->search}%")
                ->orWhereHas('merchant', fn ($m) => $m->where('business_name', 'like', "%{$this->search}%"))))
            ->when($this->filter === 'live', fn ($q) => $q->marketplaceReady())
            ->when($this->filter === 'suspended', fn ($q) => $q->where('status', Branch::STATUS_SUSPENDED))
            ->when($this->filter === 'no_location', fn ($q) => $q->where(fn ($s) => $s->whereNull('latitude')->orWhereNull('longitude')))
            ->when($this->filter === 'marketplace_off', fn ($q) => $q->where('is_marketplace_enabled', false))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.stores.index', [
            'stores' => $stores,
            'canManage' => Auth::user()->can('manage-merchants'),
        ]);
    }
}
