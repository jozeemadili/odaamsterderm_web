<?php

namespace App\Livewire\Portal\Catalog;

use App\Livewire\Forms\ProductForm;
use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Seller view of the shared catalog: browse approved products, see which
 * ones the business already stocks, and propose missing products for
 * admin approval.
 */
#[Layout('layouts.portal', ['title' => 'Product Catalog'])]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ProductForm $form;

    public bool $showForm = false;

    #[Url]
    public string $search = '';

    #[Url]
    public string $animalFilter = '';

    public function mount(): void
    {
        $this->showForm = request()->boolean('propose');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAnimalFilter(): void
    {
        $this->resetPage();
    }

    public function propose(): void
    {
        $merchantId = Auth::user()->merchant_id;

        $this->form->save([
            'status' => Product::STATUS_PENDING,
            'proposed_by_merchant_id' => $merchantId,
        ]);

        $this->showForm = false;
        session()->flash('status', 'Product proposed. You can link it to your inventory now — it goes live on the marketplace once an admin approves it.');
    }

    public function render()
    {
        $merchantId = Auth::user()->merchant_id;
        $animalIds = $this->animalFilter ? AnimalType::find($this->animalFilter)?->selfAndChildIds() : null;

        $products = Product::approved()
            ->with(['animalType', 'category.parent', 'media', 'producer.media'])
            ->withCount(['inventoryItems as stocked_count' => fn ($q) => $q->where('merchant_id', $merchantId)])
            ->when($this->search, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('brand', 'like', "%{$this->search}%")))
            ->when($animalIds, fn ($q) => $q->whereIn('animal_type_id', $animalIds))
            ->orderBy('name')
            ->paginate(20);

        $animalGroups = AnimalType::active()->whereNull('parent_id')->with(['children' => fn ($q) => $q->active()])->orderBy('sort_order')->get();

        return view('livewire.portal.catalog.index', [
            'products' => $products,
            'proposals' => Product::where('proposed_by_merchant_id', $merchantId)
                ->whereIn('status', [Product::STATUS_PENDING, Product::STATUS_REJECTED])
                ->latest()->get(),
            'animalGroups' => $animalGroups,
            'formCategories' => ProductCategory::active()->whereNull('parent_id')
                ->forAnimalType($this->form->animal_type_id ? (int) $this->form->animal_type_id : null)
                ->with(['children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->get(),
        ]);
    }
}
