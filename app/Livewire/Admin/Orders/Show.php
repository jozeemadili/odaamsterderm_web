<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Read-only order detail for support and monitoring. */
#[Layout('layouts.admin', ['title' => 'Order'])]
class Show extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace'), 403);
        $this->order = $order->load(['items', 'merchant', 'branch', 'customer', 'events.user', 'sale']);
    }

    public function render()
    {
        return view('livewire.admin.orders.show');
    }
}
