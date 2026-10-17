<?php

namespace App\Livewire\Admin\ProductCategories;

use App\Models\AnimalType;
use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'Product Categories'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $parent_id = '';

    public string $sort_order = '0';

    /** @var array<int, string> animal group ids this category applies to; empty = all */
    public array $animalTypeIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
    }

    public function startCreating(?int $parentId = null): void
    {
        $this->reset(['editingId', 'name', 'sort_order', 'animalTypeIds']);
        $this->parent_id = $parentId ? (string) $parentId : '';
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $category = ProductCategory::with('animalTypes')->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = (string) ($category->parent_id ?? '');
        $this->sort_order = (string) $category->sort_order;
        $this->animalTypeIds = $category->animalTypes->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'sort_order' => ['required', 'integer', 'min:0'],
            'animalTypeIds' => ['array'],
            'animalTypeIds.*' => ['exists:animal_types,id'],
        ], [
            'parent_id.exists' => 'Choose a top-level category (sub-categories only go one level deep).',
        ]);

        $data = [
            'name' => $this->name,
            'parent_id' => $this->parent_id ?: null,
            'sort_order' => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            $category = ProductCategory::findOrFail($this->editingId);

            if ($data['parent_id'] && $category->children()->exists()) {
                $this->addError('parent_id', 'This category has sub-categories, so it cannot be moved under another category.');

                return;
            }

            $category->update($data);
            session()->flash('status', 'Category updated.');
        } else {
            $category = ProductCategory::create($data + ['slug' => $this->uniqueSlug($this->name), 'is_active' => true]);
            session()->flash('status', 'Category added.');
        }

        // Sub-categories inherit their parent's animal restriction.
        $category->animalTypes()->sync($data['parent_id'] ? [] : $this->animalTypeIds);

        $this->reset(['showForm', 'editingId', 'name', 'parent_id', 'sort_order', 'animalTypeIds']);
    }

    public function toggleActive(int $id): void
    {
        $category = ProductCategory::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        session()->flash('status', $category->is_active
            ? 'Category reactivated.'
            : 'Category deactivated — existing products keep it, but it is hidden from new products and marketplace filters.');
    }

    protected function uniqueSlug(string $name): string
    {
        $parent = $this->parent_id ? ProductCategory::find($this->parent_id) : null;
        $base = Str::slug(($parent ? $parent->name.' ' : '').$name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.product-categories.index', [
            'categories' => ProductCategory::whereNull('parent_id')
                ->with(['animalTypes', 'children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
            'animalGroups' => AnimalType::whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
