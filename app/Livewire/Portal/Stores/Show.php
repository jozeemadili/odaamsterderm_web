<?php

namespace App\Livewire\Portal\Stores;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Store Detail'])]
class Show extends Component
{
    public Branch $store;

    public function mount(Branch $store)
    {
        abort_unless($store->merchant_id === Auth::user()->merchant_id, 403);
        $this->store = $store;
    }

    public function render()
    {
        return view('livewire.portal.stores.show', [
            'store' => $this->store,
            'summary' => $this->store->stockSummary(),
            'items' => $this->store->inventoryItems()->orderBy('name')->get(),
        ]);
    }
}
