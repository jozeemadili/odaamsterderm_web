<?php

namespace App\Livewire\Admin\ProductRequests;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\ProductRequest;
use App\Models\ProductVariant;
use App\Services\Ai\AiUnavailableException;
use App\Services\CatalogPicker;
use App\Services\LabelReader;
use App\Services\ProductRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The catalogue team's queue of products agents asked for: read the label
 * photos, link a product that is already in the catalogue, or build a new
 * one (drafted by AI from the label, checked here) — or decline with a
 * reason the agent sees.
 */
#[Layout('layouts.admin', ['title' => 'Product requests'])]
class Index extends Component
{
    #[Url]
    public string $status = ProductRequest::PENDING;

    #[Url(as: 'r')]
    public ?int $selected = null;

    /** The product being built: the AI draft, checked and corrected. */
    public array $form = [];

    public string $linkVariant = '';

    public string $rejectReason = '';

    public ?string $draftMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $first = $this->selected ?? ProductRequest::where('status', $this->status)->oldest()->value('id');
        if ($first) {
            $this->select($first);
        }
    }

    public function updatedStatus(): void
    {
        $first = ProductRequest::where('status', $this->status)->oldest()->value('id');
        $first ? $this->select($first) : $this->reset(['selected', 'form']);
    }

    public function select(int $id): void
    {
        $request = ProductRequest::findOrFail($id);
        $this->selected = $request->id;
        $this->linkVariant = '';
        $this->rejectReason = '';
        $this->draftMessage = null;
        $this->resetErrorBag();
        $this->fillForm($request->ai_draft ?? [], $request);
    }

    /** Reads the label photos with AI and fills the form for checking. */
    public function draft(LabelReader $reader): void
    {
        $request = $this->current();
        try {
            $draft = $reader->draft($request);
        } catch (AiUnavailableException $e) {
            $this->draftMessage = 'The AI could not read the label right now: '.$e->getMessage();

            return;
        }
        $request->update(['ai_draft' => $draft]);
        $this->fillForm($draft, $request);
        $this->draftMessage = 'Drafted from the label. Check every field against the photos before approving'.(! empty($draft['doubts']) ? " — the AI noted: {$draft['doubts']}" : '.');
    }

    public function addPack(): void
    {
        $this->form['pack_sizes'][] = ['name' => '', 'value' => '', 'unit' => '', 'selling_unit' => 'pack'];
    }

    public function removePack(int $i): void
    {
        unset($this->form['pack_sizes'][$i]);
        $this->form['pack_sizes'] = array_values($this->form['pack_sizes']);
    }

    public function approve(ProductRequests $requests): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.category_code' => ['required'],
            'form.animal_codes' => ['required', 'array', 'min:1'],
            'form.pack_sizes' => ['required', 'array', 'min:1'],
            'form.pack_sizes.*.name' => ['required', 'string', 'max:100'],
            'form.pack_sizes.*.selling_unit' => ['required', 'string', 'max:20'],
        ], [], ['form.name' => 'name', 'form.category_code' => 'category', 'form.animal_codes' => 'animals', 'form.pack_sizes.*.name' => 'pack size name']);

        try {
            $product = $requests->approveNew($this->current(), $this->form, Auth::user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator?->errors() ?? $e->errors());

            return;
        }

        session()->flash('status', "\"{$product->name}\" added to the catalogue and the agent's stock linked.");
        $this->next();
    }

    public function link(ProductRequests $requests): void
    {
        $this->resetErrorBag();
        $variant = ProductVariant::with('product')->find((int) $this->linkVariant);
        if (! $variant) {
            $this->addError('linkVariant', 'Choose the catalogue pack size it matches.');

            return;
        }
        $requests->linkExisting($this->current(), $variant, Auth::user());
        session()->flash('status', "Linked to \"{$variant->product->name}\"; the agent's stock now points at it.");
        $this->next();
    }

    public function reject(ProductRequests $requests): void
    {
        $this->resetErrorBag();
        try {
            $requests->reject($this->current(), Auth::user(), $this->rejectReason);
        } catch (ValidationException $e) {
            $this->addError('rejectReason', collect($e->errors())->flatten()->first());

            return;
        }
        session()->flash('status', 'Declined; the agent was told why.');
        $this->next();
    }

    protected function next(): void
    {
        $next = ProductRequest::pending()->oldest()->value('id');
        $next ? $this->select($next) : $this->reset(['selected', 'form']);
    }

    protected function current(): ProductRequest
    {
        return ProductRequest::findOrFail($this->selected);
    }

    protected function fillForm(array $d, ProductRequest $r): void
    {
        $producer = Producer::whereRaw('LOWER(name) = ?', [mb_strtolower((string) ($d['producer_name'] ?? $r->producerLabel() ?? ''))])->first();

        $this->form = [
            'name' => $d['name'] ?? $r->name,
            'producer_id' => (string) ($r->producer_id ?? $producer?->id ?? ''),
            'producer_name' => $d['producer_name'] ?? $r->producerLabel() ?? '',
            'category_code' => $d['category_code'] ?? '',
            'animal_codes' => $d['animal_codes'] ?? [],
            'life_stage_codes' => $d['life_stage_codes'] ?? [],
            'form' => $d['form'] ?? '',
            'pack_sizes' => $d['pack_sizes'] ?? [['name' => (string) $r->pack_size, 'value' => '', 'unit' => '', 'selling_unit' => 'pack']],
            'description' => $d['description'] ?? '',
            'active_ingredients' => $d['active_ingredients'] ?? '',
            'dosage' => $d['dosage'] ?? '',
            'administration_route' => $d['administration_route'] ?? [],
            'use_cases' => $d['use_cases'] ?? [],
            'disease_target' => $d['disease_target'] ?? [],
            'withdrawal_meat_days' => $d['withdrawal_meat_days'] ?? '',
            'withdrawal_eggs_days' => $d['withdrawal_eggs_days'] ?? '',
            'withdrawal_milk_days' => $d['withdrawal_milk_days'] ?? '',
            'registration_no' => $d['registration_no'] ?? '',
            'storage' => $d['storage'] ?? '',
            'prescription_required' => (bool) ($d['prescription_required'] ?? false),
            'keywords' => '',
        ];
    }

    public function render(CatalogPicker $picker, LabelReader $reader)
    {
        $current = $this->selected ? ProductRequest::with(['merchant', 'requester', 'producer', 'product', 'inventoryItem.branch', 'media'])->find($this->selected) : null;

        return view('livewire.admin.product-requests.index', [
            'requests' => ProductRequest::with(['merchant', 'media'])->where('status', $this->status)->oldest()->limit(100)->get(),
            'pendingCount' => ProductRequest::pending()->count(),
            'current' => $current,
            'matches' => $current ? $picker->similar($current->name, $current->producer_id) : collect(),
            'aiReady' => $reader->available(),
            'producers' => Producer::active()->orderBy('name')->get(['id', 'name']),
            'categories' => ProductCategory::with('parent')->where('is_active', true)->orderBy('code')->get(),
            'animals' => AnimalType::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'parent_id']),
            'stages' => LifeStage::with('animalType')->get(),
            'options' => fn (string $key) => ProductAttribute::options($key),
            'units' => ['kg', 'g', 'L', 'ml', 'doses', 'pcs', 'tablets'],
        ]);
    }
}
