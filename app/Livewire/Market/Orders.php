<?php

namespace App\Livewire\Market;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** A customer's orders and order notifications. */
#[Layout('layouts.market', ['title' => 'My orders'])]
class Orders extends Component
{
    use WithPagination;

    public function render()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->limit(8)->get();
        $user->unreadNotifications->markAsRead();

        return view('livewire.market.orders', [
            'orders' => $user->orders()->with(['branch', 'merchant'])->latest()->paginate(15),
            'notifications' => $notifications,
        ]);
    }
}
