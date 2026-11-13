<?php

namespace App\Livewire\Market;

use Livewire\Attributes\Layout;
use Livewire\Component;

/** The privacy policy the app stores link to. */
#[Layout('layouts.market', ['title' => 'Privacy policy'])]
class Privacy extends Component
{
    public function render()
    {
        return view('livewire.market.privacy');
    }
}
