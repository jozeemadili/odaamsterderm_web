<?php

namespace App\Livewire\Portal\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Seller's marketplace order inbox. */
#[Layout('layouts.portal', ['title' => 'Marketplace Orders'])]
class Index extends Component
{
    use WithPagination;

    public const TABS = [
        'open' => 'Open',
        Order::PENDING => 'New',
        'in_progress' => 'Accepted / preparing',
        Order::READY => 'Ready for pickup',
        Order::COMPLETED => 'Completed',
        'closed' => 'Rejected / cancelled',
    ];

    #[Url]
    public string $tab = 'open';

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $base = Order::where('merchant_id', Auth::user()->merchant_id);

        $orders = (clone $base)
            ->when($this->tab === 'open', fn ($q) => $q->open())
            ->when($this->tab === 'in_progress', fn ($q) => $q->whereIn('status', [Order::ACCEPTED, Order::PREPARING]))
            ->when($this->tab === 'closed', fn ($q) => $q->whereIn('status', [Order::REJECTED, Order::CANCELLED]))
            ->when(in_array($this->tab, [Order::PENDING, Order::READY, Order::COMPLETED], true), fn ($q) => $q->where('status', $this->tab))
            ->with('branch')
            ->latest()
            ->paginate(20);

        return view('livewire.portal.orders.index', [
            'orders' => $orders,
            'counts' => [
                'open' => (clone $base)->open()->count(),
                Order::PENDING => (clone $base)->where('status', Order::PENDING)->count(),
                'in_progress' => (clone $base)->whereIn('status', [Order::ACCEPTED, Order::PREPARING])->count(),
                Order::READY => (clone $base)->where('status', Order::READY)->count(),
            ],
        ]);
    }
}
