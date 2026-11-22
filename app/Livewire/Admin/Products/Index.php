<?php

namespace App\Livewire\Admin\Products;

use App\Livewire\Concerns\WithCatalogExcel;
use App\Livewire\Forms\ProductForm;
use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CatalogExcel\CatalogSheet;
use App\Services\CatalogExcel\ProductSheet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'Product Catalog'])]
class Index extends Component
{
    use WithCatalogExcel;
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

    /** '' | 'missing' (required details empty) | 'unverified' | 'verified' */
    #[Url]
    public string $quality = '';

    /** 'name' | 'least_complete' */
    #[Url]
    public string $sort = 'name';

    public ?int $reviewingId = null;

    public string $reviewNotes = '';

    protected function excelSheet(): CatalogSheet
    {
        return new ProductSheet;
    }

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);

        if (! request()->has('status') && Product::where('status', Product::STATUS_PENDING)->exists()) {
            $this->status = Product::STATUS_PENDING;
        }
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'search', 'animalFilter', 'categoryFilter', 'quality', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function startCreating(): void
    {
        $this->form->reset();
        $this->showForm = true;
    }

    public function removeImage(int $id, string $side = 'front'): void
    {
        abort_unless(auth()->user()->can('manage-catalog'), 403);
        Product::findOrFail($id)->clearMediaCollection($side === 'back' ? Product::BACK_PHOTO : 'image');
        $this->form->product?->refresh();
        session()->flash('status', ucfirst($side).' photo removed.');
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

    /** Marks the product as checked against its label or the producer's own data. */
    public function verify(int $id, string $source = 'label'): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $product = Product::findOrFail($id);
        try {
            $product->markVerified(Auth::user(), $source);
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());

            return;
        }
        $this->form->product?->refresh();
        session()->flash('status', "\"{$product->name}\" marked as verified.");
    }

    public function unverify(int $id): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        Product::findOrFail($id)->clearVerified();
        $this->form->product?->refresh();
    }

    public function startReview(int $id): void
    {
        $this->reviewingId = $id;
        $this->reviewNotes = '';
    }

    public function approve(int $id): void
    {
        $missing = Product::findOrFail($id)->refreshQuality()['missing'];
        if ($missing) {
            session()->flash('error', 'Not approved yet: fill '.implode(', ', $missing).'. Edit the product, then approve.');

            return;
        }
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
        $categoryIds = $this->categoryFilter ? ProductCategory::find($this->categoryFilter)?->selfAndChildIds() : null;

        $products = Product::query()
            ->with(['animalType.parent', 'animalTypes', 'variants', 'category.parent', 'proposedBy', 'media', 'producer'])
            ->withCount(['inventoryItems as sellers_count' => fn ($q) => $q->select(DB::raw('count(distinct merchant_id)'))])
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('brand', 'like', "%{$this->search}%")
                ->orWhere('code', $this->search)
                ->orWhere('search_keywords', 'like', "%{$this->search}%")))
            ->forAnimal($this->animalFilter)
            ->when($categoryIds, fn ($q) => $q->whereIn('product_category_id', $categoryIds))
            ->when($this->quality === 'missing', fn ($q) => $q->where('missing_required', '>', 0))
            ->when($this->quality === 'unverified', fn ($q) => $q->whereNull('verified_at'))
            ->when($this->quality === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
            ->when($this->sort === 'least_complete', fn ($q) => $q->orderBy('completeness')->orderByDesc('missing_required'))
            ->orderBy('name')
            ->paginate(20);

        $animalGroups = AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get();

        return view('livewire.admin.products.index', [
            'products' => $products,
            'check' => $this->showForm && $this->form->product ? $this->form->product->refreshQuality() : null,
            'qualityCounts' => [
                'missing' => Product::where('status', '!=', Product::STATUS_REJECTED)->where('missing_required', '>', 0)->count(),
                'verified' => Product::whereNotNull('verified_at')->count(),
            ],
            'counts' => Product::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'animalGroups' => $animalGroups,
            'filterCategories' => ProductCategory::whereNull('parent_id')->with('children')->orderBy('sort_order')->get(),
            'formCategories' => ProductCategory::active()->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->get(),
        ]);
    }
}
