<?php

namespace App\Livewire\Admin\Producers;

use App\Models\Producer;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Factories / companies that make catalog products, with their logos. */
#[Layout('layouts.admin', ['title' => 'Producers'])]
class Index extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $country = 'Tanzania';

    public string $website = '';

    public string $description = '';

    public $logo;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
    }

    public function startCreating(): void
    {
        $this->reset(['editingId', 'name', 'website', 'description', 'logo']);
        $this->country = 'Tanzania';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $producer = Producer::findOrFail($id);

        $this->editingId = $producer->id;
        $this->name = $producer->name;
        $this->country = (string) $producer->country;
        $this->website = (string) $producer->website;
        $this->description = (string) $producer->description;
        $this->logo = null;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('producers', 'name')->ignore($this->editingId)],
            'country' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name' => trim($this->name),
            'country' => trim($this->country) ?: null,
            'website' => trim($this->website) ?: null,
            'description' => trim($this->description) ?: null,
        ];

        if ($this->editingId) {
            $producer = Producer::findOrFail($this->editingId);
            $producer->update($data);
        } else {
            $producer = Producer::create($data + ['slug' => $this->uniqueSlug($data['name'])]);
        }

        if ($this->logo) {
            $producer->addMedia($this->logo->getRealPath())
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        session()->flash('status', $this->editingId ? 'Producer updated.' : 'Producer added. Link it to products in Catalog → Products.');
        $this->reset(['showForm', 'editingId', 'name', 'website', 'description', 'logo']);
    }

    public function removeLogo(int $id): void
    {
        Producer::findOrFail($id)->clearMediaCollection('logo');
        session()->flash('status', 'Logo removed.');
    }

    public function toggleActive(int $id): void
    {
        $producer = Producer::findOrFail($id);
        $producer->update(['is_active' => ! $producer->is_active]);

        session()->flash('status', $producer->is_active
            ? 'Producer reactivated.'
            : 'Producer deactivated — its products keep showing it, but it can no longer be chosen for new products.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'producer';
        $slug = $base;
        $i = 2;

        while (Producer::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.producers.index', [
            'producers' => Producer::query()
                ->with('media')
                ->withCount('products')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
