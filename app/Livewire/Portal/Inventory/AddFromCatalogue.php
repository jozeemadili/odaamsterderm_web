<?php

namespace App\Livewire\Portal\Inventory;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\Producer;
use App\Services\CatalogPicker;
use App\Support\StoreAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Add stock by picking from the central catalogue: choose the companies you
 * buy from (or the most-stocked products, or search), tick the pack sizes
 * on your shelf and set your price and quantity. No product details are
 * typed; a product that isn't in the catalogue is requested with label photos.
 */
#[Layout('layouts.portal', ['title' => 'Add from catalogue'])]
class AddFromCatalogue extends Component
{
    #[Url]
    public string $search = '';

    /** @var list<string> producer ids */
    #[Url]
    public array $producers = [];

    #[Url]
    public string $animal = '';

    public string $branchId = '';

    /** variant id => ['on' => bool, 'price' => string, 'qty' => string] */
    public array $lines = [];

    public bool $listOnline = true;

    public function mount(): void
    {
        $this->branchId = (string) (StoreAccess::branchIds()[0] ?? '');
    }

    public function toggleProducer(int $id): void
    {
        $this->producers = in_array((string) $id, $this->producers, true)
            ? array_values(array_diff($this->producers, [(string) $id]))
            : [...$this->producers, (string) $id];
    }

    public function add(CatalogPicker $picker)
    {
        $this->resetErrorBag();
        $picked = collect($this->lines)->filter(fn ($l) => ! empty($l['on']));
        if ($picked->isEmpty()) {
            $this->addError('lines', 'Tick the pack sizes you have on your shelf.');

            return null;
        }

        $missing = $picked->filter(fn ($l) => ! is_numeric($l['price'] ?? null) || ! is_numeric($l['qty'] ?? null));
        if ($missing->isNotEmpty()) {
            $this->addError('lines', 'Enter a selling price and quantity for every ticked pack size.');

            return null;
        }

        $store = Branch::whereIn('id', StoreAccess::branchIds())->findOrFail((int) $this->branchId);

        try {
            $result = $picker->addToStock(Auth::user()->merchant, $store, $picked->map(fn ($l, $variantId) => [
                'product_variant_id' => (int) $variantId,
                'unit_price' => (float) $l['price'],
                'quantity' => (float) $l['qty'],
                'list_online' => $this->listOnline,
            ])->values()->all(), Auth::user());
        } catch (ValidationException $e) {
            $this->addError('lines', collect($e->errors())->flatten()->first());

            return null;
        }

        session()->flash('status', "{$result['added']} added to {$store->name}".($result['listed'] ? ", {$result['listed']} listed on the marketplace" : '').($result['skipped'] ? ", {$result['skipped']} you already had skipped" : '').'.');
        $this->lines = [];

        return null;
    }

    public function render(CatalogPicker $picker)
    {
        $merchant = Auth::user()->merchant;
        $products = $picker->products(
            $merchant,
            $this->search,
            array_map('intval', $this->producers),
            $this->animal !== '' ? (int) $this->animal : null,
        );

        return view('livewire.portal.inventory.add-from-catalogue', [
            'products' => $products,
            'producerChips' => Producer::active()->whereHas('products', fn ($q) => $q->approved())
                ->withCount(['products' => fn ($q) => $q->approved()])->orderByDesc('products_count')->orderBy('name')->limit(40)->get(['id', 'name']),
            'animals' => AnimalType::active()->whereNull('parent_id')->orderBy('sort_order')->get(['id', 'name']),
            'stores' => Branch::whereIn('id', StoreAccess::branchIds())->orderBy('name')->get(['id', 'name']),
            'pickedCount' => collect($this->lines)->filter(fn ($l) => ! empty($l['on']))->count(),
        ]);
    }
}
