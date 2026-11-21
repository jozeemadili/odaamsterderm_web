<?php

namespace App\Livewire\Portal\ProductRequests;

use App\Models\Branch;
use App\Models\Producer;
use App\Models\ProductRequest;
use App\Services\CatalogPicker;
use App\Services\ProductRequests;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Request a product that isn't in the catalogue: what you call it, the
 * company, the pack size and two photos of the label. Close matches are
 * shown first so the same product isn't requested twice.
 */
#[Layout('layouts.portal', ['title' => 'Product requests'])]
class Index extends Component
{
    use WithFileUploads;

    #[Url]
    public string $name = '';

    public string $producer_id = '';

    public string $producer_name = '';

    public string $pack_size = '';

    public string $notes = '';

    public $front;

    public $back;

    public bool $addStock = true;

    public string $branch_id = '';

    public string $unit_price = '';

    public string $quantity = '';

    public function mount(): void
    {
        $this->branch_id = (string) (StoreAccess::branchIds()[0] ?? '');
    }

    public function submit(ProductRequests $requests): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'producer_id' => ['nullable', Rule::exists('producers', 'id')],
            'producer_name' => ['nullable', 'string', 'max:255', 'required_without:producer_id'],
            'pack_size' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'front' => ['required', 'image', 'max:8192'],
            'back' => ['required', 'image', 'max:8192'],
            'branch_id' => [$this->addStock ? 'required' : 'nullable', Rule::in(StoreAccess::branchIds())],
            'unit_price' => [$this->addStock ? 'required' : 'nullable', 'numeric', 'min:0'],
            'quantity' => [$this->addStock ? 'required' : 'nullable', 'numeric', 'min:0'],
        ], [
            'producer_name.required_without' => 'Choose the company, or type its name.',
            'front.required' => 'Add a photo of the front of the pack.',
            'back.required' => 'Add a photo of the back of the pack (the label with directions).',
        ]);

        $requests->create(Auth::user()->merchant, Auth::user(), [
            'name' => $data['name'],
            'producer_id' => $data['producer_id'] ?: null,
            'producer_name' => $data['producer_name'],
            'pack_size' => $data['pack_size'],
            'notes' => $data['notes'],
        ], $this->front, $this->back, $this->addStock ? [
            'branch_id' => (int) $data['branch_id'], 'unit_price' => $data['unit_price'], 'quantity' => $data['quantity'],
        ] : null);

        $this->reset(['name', 'producer_id', 'producer_name', 'pack_size', 'notes', 'front', 'back', 'unit_price', 'quantity']);
        session()->flash('status', 'Request sent. The catalogue team builds the product from your label photos; you\'ll get a notification when it\'s ready.'.($this->addStock ? ' Meanwhile you can sell it in your shop.' : ''));
    }

    public function render(CatalogPicker $picker)
    {
        $matches = mb_strlen(trim($this->name)) >= 3
            ? $picker->similar($this->name, $this->producer_id !== '' ? (int) $this->producer_id : null)
            : collect();

        return view('livewire.portal.product-requests.index', [
            'matches' => $matches,
            'producers' => Producer::active()->orderBy('name')->get(['id', 'name']),
            'stores' => Branch::whereIn('id', StoreAccess::branchIds())->orderBy('name')->get(['id', 'name']),
            'requests' => ProductRequest::where('merchant_id', Auth::user()->merchant_id)->with(['producer', 'product', 'media'])->latest()->limit(50)->get(),
        ]);
    }
}
