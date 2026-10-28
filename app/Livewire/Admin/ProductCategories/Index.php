<?php

namespace App\Livewire\Admin\ProductCategories;

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

    public string $name_sw = '';

    public string $description = '';

    public string $parent_id = '';

    public string $sort_order = '0';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
    }

    public function startCreating(?int $parentId = null): void
    {
        $this->reset(['editingId', 'name', 'name_sw', 'description', 'sort_order']);
        $this->parent_id = $parentId ? (string) $parentId : '';
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $category = ProductCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->name_sw = (string) $category->name_sw;
        $this->description = (string) $category->description;
        $this->parent_id = (string) ($category->parent_id ?? '');
        $this->sort_order = (string) $category->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_sw' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')->whereNull('parent_id'), Rule::notIn([$this->editingId])],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'parent_id.exists' => 'Choose a top-level category (sub-categories only go one level deep).',
        ]);

        $data = [
            'name' => $this->name,
            'name_sw' => trim($this->name_sw) ?: null,
            'description' => trim($this->description) ?: null,
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
            ProductCategory::create($data + ['slug' => $this->uniqueSlug($this->name), 'is_active' => true]);
            session()->flash('status', 'Category added.');
        }

        $this->reset(['showForm', 'editingId', 'name', 'name_sw', 'description', 'parent_id', 'sort_order']);
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
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }
}
