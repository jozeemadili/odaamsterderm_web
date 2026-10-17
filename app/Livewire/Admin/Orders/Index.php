<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Merchant;
use App\Models\Order;
use App\Services\MarketplaceMonitor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** All marketplace orders across sellers, with stale-order flags. */
#[Layout('layouts.admin', ['title' => 'Marketplace Orders'])]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $seller = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace'), 403);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'seller', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $orders = Order::query()
            ->with(['merchant', 'branch'])
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when($this->status === 'stale', fn ($q) => $q->where(fn ($s) => $s
                ->where(fn ($p) => $p->where('status', Order::PENDING)->where('created_at', '<', now()->subHours(MarketplaceMonitor::STALE_PENDING_HOURS)))
                ->orWhere(fn ($r) => $r->where('status', Order::READY)->where('ready_at', '<', now()->subHours(MarketplaceMonitor::STALE_READY_HOURS)))))
            ->when(! in_array($this->status, ['open', 'stale', 'all'], true), fn ($q) => $q->where('status', $this->status))
            ->when($this->seller, fn ($q) => $q->where('merchant_id', $this->seller))
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s->where('reference', 'like', "%{$this->search}%")->orWhere('customer_name', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'sellers' => Merchant::orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => ['open' => 'Open', 'stale' => 'Stale', ...Order::LABELS, 'all' => 'All'],
        ]);
    }
}
