<?php

namespace App\Livewire\Market;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** How to delete an account: the link Google Play asks for. */
#[Layout('layouts.market', ['title' => 'Delete your account'])]
class DeleteAccountInfo extends Component
{
    public function render()
    {
        return view('livewire.market.delete-account-info');
    }
}
