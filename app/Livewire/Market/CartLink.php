<?php

namespace App\Livewire\Market;

use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/** Cart link with item count in the marketplace header (customers only). */
class CartLink extends Component
{
    #[On('cart-updated')]
    public function refresh(): void {}

    public function render(OrderService $orders)
    {
        $user = Auth::user();

        return view('livewire.market.cart-link', [
            'count' => $user?->isCustomer() ? $orders->cartCount($user) : 0,
            'unread' => $user?->isCustomer() ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
