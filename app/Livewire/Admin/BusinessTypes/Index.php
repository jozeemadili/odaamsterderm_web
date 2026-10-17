<?php

namespace App\Livewire\Admin\BusinessTypes;

use App\Models\BusinessType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Business Types'])]
class Index extends Component
{
    public string $name = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $editingName = '';

    public function create()
    {
        $this->validate(['name' => ['required', 'string', 'max:255', 'unique:business_types,name']]);

        BusinessType::create(['name' => $this->name, 'is_active' => true]);

        $this->reset(['name', 'showForm']);
        session()->flash('status', 'Business type added.');
    }

    public function startEditing(int $id): void
    {
        $type = BusinessType::findOrFail($id);
        $this->editingId = $type->id;
        $this->editingName = $type->name;
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->editingName = '';
    }

    public function saveEditing(): void
    {
        $this->validate([
            'editingName' => ['required', 'string', 'max:255', 'unique:business_types,name,'.$this->editingId],
        ]);

        BusinessType::findOrFail($this->editingId)->update(['name' => $this->editingName]);

        $this->cancelEditing();
        session()->flash('status', 'Business type updated.');
    }

    public function toggleActive(int $id): void
    {
        $type = BusinessType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        session()->flash('status', $type->is_active
            ? 'Business type reactivated — it will appear as an option again.'
            : 'Business type deactivated — existing merchants keep it, but it no longer appears as an option for new records.');
    }

    public function render()
    {
        return view('livewire.admin.business-types.index', [
            'types' => BusinessType::orderBy('name')->get(),
        ]);
    }
}
