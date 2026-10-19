<?php

namespace App\Livewire\Portal\Orders;

use App\Models\Order;
use App\Services\OrderService;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One marketplace order with the seller's next actions. */
#[Layout('layouts.portal', ['title' => 'Order'])]
class Show extends Component
{
    public Order $order;

    public bool $rejecting = false;

    public string $reason = '';

    public function mount(Order $order): void
    {
        abort_unless($order->merchant_id === Auth::user()->merchant_id && StoreAccess::allows($order->branch_id), 403);
        $this->order = $order;
    }

    public function advance(string $status, OrderService $orders): void
    {
        try {
            $this->order = $orders->advance($this->order, $status, Auth::user(), $status === Order::REJECTED ? $this->reason : null);
            $this->rejecting = false;
            $this->reason = '';
            session()->flash('status', "Order {$this->order->reference}: {$this->order->statusLabel()}.");
        } catch (ValidationException $e) {
            $this->addError('reason', collect($e->errors())->flatten()->first());
        }
    }

    public function render()
    {
        $this->order->load(['items.inventoryItem', 'branch', 'events.user', 'sale']);

        return view('livewire.portal.orders.show');
    }
}
