<?php

namespace App\Livewire\Market;

use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Basket grouped by pickup store; checkout places one order per store. */
#[Layout('layouts.market', ['title' => 'Cart'])]
class Cart extends Component
{
    public string $note = '';

    public string $phone = '';

    public function mount(): void
    {
        $this->phone = (string) Auth::user()->phone;
    }

    public function setQuantity(int $offerId, $quantity, OrderService $orders): void
    {
        try {
            $orders->setCartQuantity(Auth::user(), $offerId, (float) $quantity);
            $this->resetErrorBag();
        } catch (ValidationException $e) {
            $this->addError('cart', collect($e->errors())->flatten()->first());
        }

        $this->dispatch('cart-updated');
    }

    public function remove(int $offerId, OrderService $orders): void
    {
        $orders->setCartQuantity(Auth::user(), $offerId, 0);
        $this->dispatch('cart-updated');
    }

    public function checkout(OrderService $orders)
    {
        $this->validate([
            'phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['phone' => 'phone number']);

        try {
            $placed = $orders->checkout(Auth::user(), $this->note, $this->phone);
        } catch (ValidationException $e) {
            $this->addError('cart', collect($e->errors())->flatten()->first());

            return null;
        }

        $this->dispatch('cart-updated');
        session()->flash('status', $placed->count() === 1
            ? "Order {$placed->first()->reference} placed. We'll notify you when the store accepts it."
            : $placed->count().' orders placed — one per store. We\'ll notify you as each store accepts.');

        return $this->redirectRoute($placed->count() === 1 ? 'market.order' : 'market.orders', $placed->count() === 1 ? ['order' => $placed->first()] : []);
    }

    public function render(OrderService $orders)
    {
        $groups = $orders->cartByStore(Auth::user());

        return view('livewire.market.cart', [
            'groups' => $groups,
            'total' => collect($groups)->sum('total'),
            'hasProblems' => collect($groups)->flatMap(fn ($g) => $g['lines'])->contains(fn ($l) => $l['problem'] !== null),
        ]);
    }
}
