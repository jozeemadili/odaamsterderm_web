<?php

namespace App\Livewire\Market;

use App\Livewire\Concerns\AddsToCart;
use App\Models\HatchSchedule;
use App\Models\Order;
use App\Services\CareSuggestions;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One order: items, pickup store, status timeline, cancel. */
#[Layout('layouts.market', ['title' => 'Order'])]
class OrderShow extends Component
{
    use AddsToCart;

    public Order $order;

    public bool $confirmingCancel = false;

    public string $reason = '';

    public function mount(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 404);
        $this->order = $order;
    }

    /** Adds a suggested product to the cart at the quantity sized for the chicks. */
    public function addSuggestion(int $offerId, int $quantity, OrderService $orders)
    {
        $this->quantities[$offerId] = (string) max(1, $quantity);

        return $this->addToCart($offerId, $orders);
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
        $this->order->load(['items.hatchSchedule.chickType', 'branch.merchant', 'events']);

        // A chick booking without add-ons: what the chicks will still need, from the same store.
        $chickLine = $this->order->items->first(fn ($i) => $i->hatch_schedule_id !== null);
        $suggestions = $chickLine?->hatchSchedule && in_array($this->order->status, Order::OPEN, true)
            && ! $this->order->items->contains(fn ($i) => $i->source === 'care_plan')
            ? app(CareSuggestions::class)->forBooking(
                $chickLine->hatchSchedule,
                intdiv((int) $chickLine->quantity, HatchSchedule::boxSize()),
                $this->order->branch_id,
                $this->order->items->pluck('product_id')->filter()->all(),
            )
            : [];

        return view('livewire.market.order-show', ['suggestions' => $suggestions])->title($this->order->reference);
    }
}
