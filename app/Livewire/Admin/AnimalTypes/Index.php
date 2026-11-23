<?php

namespace App\Livewire\Admin\AnimalTypes;

use App\Livewire\Concerns\WithCatalogExcel;
use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Services\CatalogExcel\AnimalTypeSheet;
use App\Services\CatalogExcel\CatalogSheet;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Animal Types'])]
class Index extends Component
{
    use WithCatalogExcel;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $name_sw = '';

    public string $parent_id = '';

    public string $sort_order = '0';

    public string $description = '';

    public $image;

    public string $search = '';

    protected function excelSheet(): CatalogSheet
    {
        return new AnimalTypeSheet;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
    }

    public function startCreating(?int $parentId = null): void
    {
        $this->reset(['editingId', 'name', 'name_sw', 'sort_order', 'description', 'image']);
        $this->resetErrorBag();
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
        $this->description = (string) $type->description;
        $this->image = null;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_sw' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('animal_types', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ], [
            'parent_id.exists' => 'Choose a top-level group (groups only go one level deep).',
        ]);

        $data = [
            'name' => $this->name,
            'name_sw' => trim($this->name_sw) ?: null,
            'parent_id' => $this->parent_id ?: null,
            'sort_order' => (int) $this->sort_order,
            'description' => trim($this->description) ?: null,
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
            $type = AnimalType::create($data + ['slug' => $this->uniqueSlug($this->name), 'is_active' => true]);
            session()->flash('status', 'Animal type added.');
        }

        if ($this->image) {
            $type->addMedia($this->image->getRealPath())->usingFileName($this->image->getClientOriginalName())->toMediaCollection('image');
        }

        $this->reset(['showForm', 'editingId', 'name', 'name_sw', 'parent_id', 'sort_order', 'description', 'image']);
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
        $search = trim($this->search);

        return view('livewire.admin.animal-types.index', [
            'groups' => AnimalType::whereNull('parent_id')
                ->with(['media', 'lifeStages.media', 'children' => fn ($q) => $q->with('media')->withCount('taggedProducts')])
                ->withCount(['taggedProducts', 'lifeStages'])
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('name_sw', 'like', "%{$search}%")
                    ->orWhereHas('children', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('name_sw', 'like', "%{$search}%"))))
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
            'stats' => [
                'groups' => AnimalType::whereNull('parent_id')->count(),
                'types' => AnimalType::whereNotNull('parent_id')->count(),
                'stages' => LifeStage::count(),
                'no_image' => AnimalType::whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'image'))->count(),
            ],
        ]);
    }
}
