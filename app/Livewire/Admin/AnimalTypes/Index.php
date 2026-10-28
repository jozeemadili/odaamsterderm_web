<?php

namespace App\Livewire\Admin\AnimalTypes;

use App\Models\AnimalType;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Animal Types'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $name_sw = '';

    public string $parent_id = '';

    public string $sort_order = '0';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
    }

    public function startCreating(?int $parentId = null): void
    {
        $this->reset(['editingId', 'name', 'name_sw', 'sort_order']);
        $this->parent_id = $parentId ? (string) $parentId : '';
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $type = AnimalType::findOrFail($id);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->name_sw = (string) $type->name_sw;
        $this->parent_id = (string) ($type->parent_id ?? '');
        $this->sort_order = (string) $type->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_sw' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('animal_types', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'parent_id.exists' => 'Choose a top-level group (groups only go one level deep).',
        ]);

        $data = [
            'name' => $this->name,
            'name_sw' => trim($this->name_sw) ?: null,
            'parent_id' => $this->parent_id ?: null,
            'sort_order' => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            $type = AnimalType::findOrFail($this->editingId);

            if ($data['parent_id'] && $type->children()->exists()) {
                $this->addError('parent_id', 'This group has animal types under it, so it cannot be moved under another group.');

                return;
            }

            $type->update($data);
            session()->flash('status', 'Animal type updated.');
        } else {
            AnimalType::create($data + ['slug' => $this->uniqueSlug($this->name), 'is_active' => true]);
            session()->flash('status', 'Animal type added.');
        }

        $this->reset(['showForm', 'editingId', 'name', 'name_sw', 'parent_id', 'sort_order']);
    }

    public function toggleActive(int $id): void
    {
        $type = AnimalType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        session()->flash('status', $type->is_active
            ? 'Animal type reactivated.'
            : 'Animal type deactivated — existing products keep it, but it is hidden from new products and marketplace filters.');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'animal';
        $slug = $base;
        $i = 2;

        while (AnimalType::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.animal-types.index', [
            'groups' => AnimalType::whereNull('parent_id')
                ->with(['lifeStages', 'children' => fn ($q) => $q->with('lifeStages')->withCount('products')])
                ->withCount('products')
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }
}
