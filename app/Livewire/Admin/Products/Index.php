<?php

namespace App\Livewire\Admin\Products;

use App\Livewire\Forms\ProductForm;
use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'Product Catalog'])]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ProductForm $form;

    public bool $showForm = false;

    #[Url]
    public string $status = 'approved';

    #[Url]
    public string $search = '';

    #[Url]
    public string $animalFilter = '';

    #[Url]
    public string $categoryFilter = '';

    public ?int $reviewingId = null;

    public string $reviewNotes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);

        if (! request()->has('status') && Product::where('status', Product::STATUS_PENDING)->exists()) {
            $this->status = Product::STATUS_PENDING;
        }
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'search', 'animalFilter', 'categoryFilter'], true)) {
            $this->resetPage();
        }
    }

    public function startCreating(): void
    {
        $this->form->reset();
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $this->form->setProduct(Product::findOrFail($id));
        $this->showForm = true;
    }

    public function save(): void
    {
        $isNew = $this->form->product === null;

        // Admin-created products are approved immediately.
        $this->form->save($isNew ? [
            'status' => Product::STATUS_APPROVED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ] : []);

        $this->showForm = false;
        session()->flash('status', $isNew ? 'Product added to the catalog.' : 'Product updated.');
    }

    public function startReview(int $id): void
    {
        $this->reviewingId = $id;
        $this->reviewNotes = '';
    }

    public function approve(int $id): void
    {
        $this->review($id, Product::STATUS_APPROVED);
        session()->flash('status', 'Product approved — sellers can now list it on the marketplace.');
    }

    public function reject(): void
    {
        $this->validate(['reviewNotes' => ['required', 'string', 'max:1000']], ['reviewNotes.required' => 'Tell the seller why it was rejected.']);

        $this->review($this->reviewingId, Product::STATUS_REJECTED);
        session()->flash('status', 'Product rejected.');
    }

    protected function review(int $id, string $status): void
    {
        Product::findOrFail($id)->update([
            'status' => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $this->reviewNotes ?: null,
        ]);

        $this->reset(['reviewingId', 'reviewNotes']);
    }

    public function render()
    {
        $animalIds = $this->animalFilter ? AnimalType::find($this->animalFilter)?->selfAndChildIds() : null;
        $categoryIds = $this->categoryFilter ? ProductCategory::find($this->categoryFilter)?->selfAndChildIds() : null;

        $products = Product::query()
            ->with(['animalType.parent', 'category.parent', 'proposedBy', 'media', 'producer'])
            ->withCount(['inventoryItems as sellers_count' => fn ($q) => $q->select(DB::raw('count(distinct merchant_id)'))])
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('brand', 'like', "%{$this->search}%")))
            ->when($animalIds, fn ($q) => $q->whereIn('animal_type_id', $animalIds))
            ->when($categoryIds, fn ($q) => $q->whereIn('product_category_id', $categoryIds))
            ->orderBy('name')
            ->paginate(20);

        $animalGroups = AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get();

        return view('livewire.admin.products.index', [
            'products' => $products,
            'counts' => Product::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'animalGroups' => $animalGroups,
            'filterCategories' => ProductCategory::whereNull('parent_id')->with('children')->orderBy('sort_order')->get(),
            'formCategories' => ProductCategory::active()->whereNull('parent_id')
                ->forAnimalType($this->form->animal_type_id ? (int) $this->form->animal_type_id : null)
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->get(),
        ]);
    }
}
