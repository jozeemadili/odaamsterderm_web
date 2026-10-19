<?php

namespace App\Livewire\Portal\Stores;

use App\Models\Branch;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal', ['title' => 'Stores'])]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public bool $is_primary = false;

    public function create()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $merchantId = Auth::user()->merchant_id;

        if ($this->is_primary) {
            Branch::where('merchant_id', $merchantId)->update(['is_primary' => false]);
        }

        $store = Branch::create([
            'merchant_id' => $merchantId,
            'name' => $this->name,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'is_primary' => $this->is_primary,
        ]);

        session()->flash('status', 'Store added. Now pin its location on the map so customers can find it.');

        return $this->redirectRoute('portal.stores.show', ['store' => $store, 'setup' => 1], navigate: false);
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;

        $stores = Branch::where('merchant_id', $merchantId)
            ->whereIn('id', StoreAccess::branchIds())
            ->withCount('inventoryItems')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return view('livewire.portal.stores.index', compact('stores'));
    }
}
