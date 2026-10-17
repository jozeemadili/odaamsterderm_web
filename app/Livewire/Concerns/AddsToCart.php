<?php

namespace App\Livewire\Concerns;

use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * "Add to cart" on marketplace pages. Guests are sent to sign in and come
 * back; only customer accounts can order.
 */
trait AddsToCart
{
    /** @var array<int, string> offer id → quantity typed in the row */
    public array $quantities = [];

    public ?string $cartMessage = null;

    public function addToCart(int $offerId, OrderService $orders)
    {
        $user = Auth::user();

        if (! $user) {
            session()->put('url.intended', url()->previous());

            return $this->redirectRoute('login');
        }

        if (! $user->isCustomer()) {
            $this->addError('cart', 'Sign in with a customer account to order. Seller and staff accounts cannot place marketplace orders.');

            return null;
        }

        $quantity = (float) ($this->quantities[$offerId] ?? 1);

        try {
            $orders->addToCart($user, $offerId, $quantity > 0 ? $quantity : 1);
        } catch (ValidationException $e) {
            $this->addError('cart', collect($e->errors())->flatten()->first());

            return null;
        }

        $this->resetErrorBag('cart');
        $this->cartMessage = 'Added to your cart.';
        $this->dispatch('cart-updated');

        return null;
    }
}
