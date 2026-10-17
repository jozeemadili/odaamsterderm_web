<?php

namespace App\Livewire\Market;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One order: items, pickup store, status timeline, cancel. */
#[Layout('layouts.market', ['title' => 'Order'])]
class OrderShow extends Component
{
    public Order $order;

    public bool $confirmingCancel = false;

    public string $reason = '';

    public function mount(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 404);
        $this->order = $order;
    }

    public function cancel(OrderService $orders): void
    {
        try {
            $this->order = $orders->cancelByCustomer($this->order, Auth::user(), $this->reason ?: null);
            $this->confirmingCancel = false;
            session()->flash('status', 'Order cancelled.');
        } catch (ValidationException $e) {
            $this->addError('reason', collect($e->errors())->flatten()->first());
        }
    }

    public function render()
    {
        $this->order->load(['items', 'branch.merchant', 'events']);

        return view('livewire.market.order-show')->title($this->order->reference);
    }
}
