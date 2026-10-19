<?php

namespace App\Livewire\Portal;

use App\Models\Branch;
use App\Support\StoreAccess;
use Livewire\Component;

/** "Working in: Mwenge store" — picks the store the portal shows and operates. */
class StoreSwitcher extends Component
{
    public string $selected = '';

    public function mount(): void
    {
        $this->selected = (string) (StoreAccess::currentId() ?? '');
    }

    public function updatedSelected(string $value): void
    {
        StoreAccess::choose($value === '' ? null : (int) $value);

        $this->redirect(url()->previous(), navigate: false);
    }

    public function render()
    {
        $ids = StoreAccess::branchIds();

        return view('livewire.portal.store-switcher', [
            'stores' => Branch::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
