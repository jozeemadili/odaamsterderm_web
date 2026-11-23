<?php

namespace App\Livewire\Admin\Producers;

use App\Models\ChickContract;
use App\Models\HatchSchedule;
use App\Models\InventoryItem;
use App\Models\Producer;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Everything about one producer: logo and contact details, what they make
 * (by category and animal), which agents stock them, their hatchery
 * contracts if they supply chicks, and their catalogue products.
 */
#[Layout('layouts.admin', ['title' => 'Producer'])]
class Show extends Component
{
    use WithFileUploads;
    use WithPagination;

    public Producer $producer;

    public $logo;

    public bool $editing = false;

    public array $details = [];

    /** '' | missing | verified */
    #[Url]
    public string $products = '';

    public function mount(Producer $producer): void
    {
        abort_unless(Auth::user()->can('manage-catalog'), 403);
        $this->producer = $producer;
        $this->fillDetails();
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => ['image', 'max:2048']]);
        $this->producer->addMedia($this->logo->getRealPath())->usingFileName($this->logo->getClientOriginalName())->toMediaCollection('logo');
        $this->reset('logo');
        $this->producer->refresh();
        session()->flash('status', 'Logo saved.');
    }

    public function removeLogo(): void
    {
        $this->producer->clearMediaCollection('logo');
        $this->producer->refresh();
    }

    public function saveDetails(): void
    {
        $this->validate([
            'details.name' => ['required', 'string', 'max:255', Rule::unique('producers', 'name')->ignore($this->producer->id)],
            'details.country' => ['nullable', 'string', 'max:100'],
            'details.role' => ['nullable', 'string', 'max:255'],
            'details.deals_in' => ['nullable', Rule::in(array_keys(Producer::DEALS))],
            'details.website' => ['nullable', 'url', 'max:255'],
            'details.phone' => ['nullable', 'string', 'max:50'],
            'details.location' => ['nullable', 'string', 'max:255'],
            'details.description' => ['nullable', 'string', 'max:2000'],
        ], [], ['details.name' => 'name', 'details.website' => 'website']);

        $this->producer->update(collect($this->details)->map(fn ($v) => trim((string) $v) ?: null)->all());
        $this->editing = false;
        session()->flash('status', 'Details saved.');
    }

    public function toggleActive(): void
    {
        $this->producer->update(['is_active' => ! $this->producer->is_active]);
    }

    public function toggleMarketplace(): void
    {
        $this->producer->update(['on_marketplace' => ! $this->producer->on_marketplace]);
    }

    public function updatingProducts(): void
    {
        $this->resetPage();
    }

    protected function fillDetails(): void
    {
        $p = $this->producer;
        $this->details = [
            'name' => $p->name, 'country' => (string) $p->country, 'role' => (string) $p->role, 'deals_in' => (string) $p->deals_in,
            'website' => (string) $p->website, 'phone' => (string) $p->phone, 'location' => (string) $p->location, 'description' => (string) $p->description,
        ];
    }

    public function render()
    {
        $producer = $this->producer->load('media');
        $all = Product::where('products.producer_id', $producer->id);
        $productIds = (clone $all)->select('products.id');

        $hatchery = $producer->makesChicks() || $producer->hatchSchedules()->exists();

        return view('livewire.admin.producers.show', [
            'producer' => $producer,
            'stats' => [
                'products' => (clone $all)->count(),
                'approved' => (clone $all)->where('status', Product::STATUS_APPROVED)->count(),
                'verified' => (clone $all)->whereNotNull('verified_at')->count(),
                'missing' => (clone $all)->where('missing_required', '>', 0)->count(),
                'sellers' => InventoryItem::whereIn('product_id', $productIds)->distinct()->count('merchant_id'),
                'average' => (int) round((float) (clone $all)->avg('completeness')),
            ],
            'categories' => (clone $all)->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw('product_categories.name, count(*) as total')->groupBy('product_categories.name')->orderByDesc('total')->limit(8)->pluck('total', 'name'),
            'animals' => DB::table('animal_type_product')
                ->join('animal_types', 'animal_types.id', '=', 'animal_type_product.animal_type_id')
                ->whereIn('animal_type_product.product_id', $productIds)
                ->selectRaw('animal_types.name, count(distinct animal_type_product.product_id) as total')->groupBy('animal_types.name')->orderByDesc('total')->limit(8)->pluck('total', 'name'),
            'agents' => InventoryItem::whereIn('product_id', $productIds)
                ->join('merchants', 'merchants.id', '=', 'inventory_items.merchant_id')
                ->selectRaw('merchants.business_name, count(distinct inventory_items.product_id) as total')
                ->groupBy('merchants.business_name')->orderByDesc('total')->limit(8)->pluck('total', 'business_name'),
            'hatchery' => $hatchery ? [
                'types' => $producer->chickTypes()->pluck('name'),
                'contracts' => ChickContract::where('producer_id', $producer->id)->where('is_active', true)->with('merchant')->get(),
                'upcoming' => HatchSchedule::where('producer_id', $producer->id)->bookable()->count(),
                'next' => HatchSchedule::where('producer_id', $producer->id)->bookable()->orderBy('hatch_date')->value('hatch_date'),
            ] : null,
            'list' => (clone $all)->with(['media', 'category', 'variants'])
                ->when($this->products === 'missing', fn ($q) => $q->where('missing_required', '>', 0))
                ->when($this->products === 'verified', fn ($q) => $q->whereNotNull('verified_at'))
                ->orderBy('name')->paginate(12),
        ])->title($producer->name.' · Producers');
    }
}
