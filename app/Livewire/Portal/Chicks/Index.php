<?php

namespace App\Livewire\Portal\Chicks;

use App\Models\Branch;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Merchant;
use App\Models\OrderItem;
use App\Models\Producer;
use App\Services\ChickService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * An agent's day-old chick sales: the hatcheries it has contracts with
 * (chick type, pickup stores, arrival weekdays, cap of boxes, price) and
 * the order days generated from them, with each day's bookings. Platform
 * staff see the same page for any agent.
 */
#[Layout('layouts.portal', ['title' => 'Day-old chicks'])]
class Index extends Component
{
    #[Url]
    public string $tab = 'days';

    /** Platform staff: the agent being managed. */
    #[Url]
    public string $agent = '';

    #[Url]
    public string $contract = '';

    #[Url]
    public bool $showPast = false;

    /** Contract form */
    public bool $showContractForm = false;

    public ?int $editingContractId = null;

    public string $producer_id = '';

    public string $new_hatchery = '';

    public string $chick_type_id = '';

    public array $branch_ids = [];

    public array $weekdays = ['1', '4'];

    public string $cap_boxes = '100';

    public string $contract_price = '';

    public bool $is_active = true;

    /** Order day form (an extra day, or changes to one) */
    public bool $showDayForm = false;

    public ?int $editingDayId = null;

    public string $day_contract_id = '';

    public string $day_date = '';

    public string $day_cap = '';

    public string $day_price = '';

    public string $day_notes = '';

    /** Bookings of one day */
    public ?int $bookingsFor = null;

    public function mount(ChickService $chicks): void
    {
        abort_unless($chicks->canManage(Auth::user()), 403);

        if (! Auth::user()->isInternal()) {
            $this->agent = (string) Auth::user()->merchant_id;
        } elseif ($this->agent === '') {
            $this->agent = (string) ($chicks->platformAgent()?->id ?? Merchant::marketplaceApproved()->orderBy('business_name')->value('id') ?? '');
        }
    }

    protected function merchant(): Merchant
    {
        $id = Auth::user()->isInternal() ? (int) $this->agent : Auth::user()->merchant_id;

        return Merchant::findOrFail($id);
    }

    protected function ownContract(int $id): ChickContract
    {
        return ChickContract::where('merchant_id', $this->merchant()->id)->findOrFail($id);
    }

    protected function ownDay(int $id): HatchSchedule
    {
        return HatchSchedule::where('merchant_id', $this->merchant()->id)->findOrFail($id);
    }

    public function updatedAgent(): void
    {
        abort_unless(Auth::user()->isInternal(), 403);
        $this->reset(['contract', 'bookingsFor', 'showContractForm', 'showDayForm']);
    }

    // --- Contracts ---------------------------------------------------------

    public function newContract(): void
    {
        $this->resetContractForm();
        $this->showContractForm = true;
    }

    public function editContract(int $id): void
    {
        $c = $this->ownContract($id);
        $this->resetContractForm();
        $this->editingContractId = $c->id;
        $this->producer_id = (string) $c->producer_id;
        $this->chick_type_id = (string) $c->chick_type_id;
        $this->branch_ids = array_map('strval', $c->branch_ids ?? []);
        $this->weekdays = array_map('strval', $c->weekdays);
        $this->cap_boxes = (string) $c->cap_boxes;
        $this->contract_price = $c->price_per_chick !== null ? rtrim(rtrim((string) $c->price_per_chick, '0'), '.') : '';
        $this->is_active = $c->is_active;
        $this->showContractForm = true;
    }

    public function saveContract(ChickService $chicks): void
    {
        $this->validate([
            'producer_id' => ['nullable'],
            'new_hatchery' => ['nullable', 'string', 'max:255'],
            'chick_type_id' => ['required', Rule::exists('chick_types', 'id')],
            'weekdays' => ['required', 'array', 'min:1'],
            'cap_boxes' => ['required', 'integer', 'min:1', 'max:100000'],
            'contract_price' => ['nullable', 'numeric', 'min:0'],
        ], ['weekdays.required' => 'Choose the days the chicks arrive.'],
            ['chick_type_id' => 'chick type', 'cap_boxes' => 'cap']);

        $isNew = $this->producer_id === 'new' || $this->producer_id === '';
        if ($isNew && trim($this->new_hatchery) === '') {
            $this->addError('producer_id', 'Choose the hatchery, or type the name of a new one.');

            return;
        }
        $producerId = $isNew ? $this->createHatchery() : (int) $this->producer_id;

        $chicks->saveContract($this->merchant(), [
            'producer_id' => $producerId,
            'chick_type_id' => (int) $this->chick_type_id,
            'branch_ids' => $this->branch_ids,
            'weekdays' => $this->weekdays,
            'cap_boxes' => (int) $this->cap_boxes,
            'price_per_chick' => $this->contract_price,
            'is_active' => $this->is_active,
        ], $this->editingContractId ? $this->ownContract($this->editingContractId) : null);

        session()->flash('status', $this->editingContractId ? 'Contract updated. Upcoming order days follow it.' : 'Contract added. Order days are ready for customers to book.');
        $this->resetContractForm();
    }

    /** A hatchery not on the platform yet, typed in by the agent. */
    protected function createHatchery(): int
    {
        $name = trim($this->new_hatchery);
        $existing = Producer::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($existing) {
            return $existing->id;
        }

        $base = Str::slug($name) ?: 'hatchery';
        $slug = $base;
        for ($i = 2; Producer::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return Producer::create([
            'name' => $name,
            'slug' => $slug,
            'role' => 'Hatchery (day-old chicks)',
            'deals_in' => Producer::DEALS_CHICKS,
            'country' => 'Tanzania',
            'is_active' => true,
        ])->id;
    }

    public function toggleContract(int $id, ChickService $chicks): void
    {
        $c = $this->ownContract($id);
        $chicks->saveContract($this->merchant(), [
            'producer_id' => $c->producer_id, 'chick_type_id' => $c->chick_type_id, 'branch_ids' => $c->branch_ids,
            'weekdays' => $c->weekdays, 'cap_boxes' => $c->cap_boxes, 'price_per_chick' => $c->price_per_chick === null ? '' : (string) $c->price_per_chick,
            'is_active' => ! $c->is_active,
        ], $c);
        session()->flash('status', $c->is_active ? 'Contract paused; its upcoming days are closed.' : 'Contract resumed.');
    }

    protected function resetContractForm(): void
    {
        $this->reset(['showContractForm', 'editingContractId', 'producer_id', 'new_hatchery', 'chick_type_id', 'branch_ids', 'contract_price']);
        $this->weekdays = ['1', '4'];
        $this->cap_boxes = '100';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    // --- Order days --------------------------------------------------------

    public function newDay(): void
    {
        $this->resetDayForm();
        $this->day_contract_id = $this->contract;
        $this->showDayForm = true;
    }

    public function editDay(int $id): void
    {
        $d = $this->ownDay($id);
        $this->resetDayForm();
        $this->editingDayId = $d->id;
        $this->day_contract_id = (string) $d->chick_contract_id;
        $this->day_date = $d->hatch_date->toDateString();
        $this->day_cap = (string) $d->capBoxes();
        $this->day_price = $d->price_per_chick !== null ? rtrim(rtrim((string) $d->price_per_chick, '0'), '.') : '';
        $this->day_notes = (string) $d->notes;
        $this->showDayForm = true;
    }

    public function updatedDayContractId(): void
    {
        if (! $this->editingDayId && $this->day_contract_id !== '') {
            $c = $this->ownContract((int) $this->day_contract_id);
            $this->day_cap = (string) $c->cap_boxes;
            $this->day_price = $c->price_per_chick !== null ? rtrim(rtrim((string) $c->price_per_chick, '0'), '.') : '';
        }
    }

    public function saveDay(ChickService $chicks): void
    {
        $this->validate([
            'day_contract_id' => ['required'],
            'day_date' => ['required', 'date'],
            'day_cap' => ['required', 'integer', 'min:1', 'max:100000'],
            'day_price' => ['nullable', 'numeric', 'min:0'],
            'day_notes' => ['nullable', 'string', 'max:500'],
        ], [], ['day_contract_id' => 'hatchery', 'day_date' => 'day', 'day_cap' => 'cap']);

        $chicks->saveOrderDay($this->ownContract((int) $this->day_contract_id), [
            'hatch_date' => $this->day_date,
            'cap_boxes' => (int) $this->day_cap,
            'price_per_chick' => $this->day_price,
            'notes' => $this->day_notes,
        ], $this->editingDayId ? $this->ownDay($this->editingDayId) : null);

        session()->flash('status', $this->editingDayId ? 'Order day updated.' : 'Order day added.');
        $this->resetDayForm();
    }

    public function toggleClosed(int $id): void
    {
        $d = $this->ownDay($id);
        $d->update(['is_closed' => ! $d->is_closed]);
        session()->flash('status', $d->is_closed ? 'Booking closed for that day.' : 'Booking reopened for that day.');
    }

    public function deleteDay(int $id): void
    {
        $d = $this->ownDay($id);
        if ($d->orderItems()->exists()) {
            session()->flash('error', 'This day has bookings, so it can\'t be deleted. Close it instead.');

            return;
        }
        $d->delete();
        session()->flash('status', 'Order day deleted. It will not come back.');
    }

    public function showBookings(int $id): void
    {
        $this->bookingsFor = $this->bookingsFor === $id ? null : $id;
    }

    protected function resetDayForm(): void
    {
        $this->reset(['showDayForm', 'editingDayId', 'day_contract_id', 'day_date', 'day_cap', 'day_price', 'day_notes']);
        $this->resetErrorBag();
    }

    public function render(ChickService $chicks)
    {
        $chicks->ensureUpcoming();
        $merchant = $this->agent !== '' ? $this->merchant() : null;

        $contracts = $merchant
            ? ChickContract::where('merchant_id', $merchant->id)->with(['producer.media', 'chickType'])
                ->withCount(['orderDays as upcoming_days' => fn ($q) => $q->whereDate('hatch_date', '>=', today())])
                ->orderByDesc('is_active')->get()->sortBy(fn ($c) => $c->producer->name.' '.$c->chickType->sort_order)->values()
            : collect();

        $days = $merchant
            ? HatchSchedule::where('merchant_id', $merchant->id)
                ->withBooked()
                ->with(['producer', 'chickType', 'contract'])
                ->when(! $this->showPast, fn ($q) => $q->whereDate('hatch_date', '>=', today()))
                ->when($this->contract !== '', fn ($q) => $q->where('chick_contract_id', $this->contract))
                ->orderBy('hatch_date', $this->showPast ? 'desc' : 'asc')
                ->limit(200)
                ->get()
            : collect();

        $bookings = $this->bookingsFor
            ? OrderItem::where('hatch_schedule_id', $this->bookingsFor)->with('order.branch')->latest()->get()
            : collect();

        return view('livewire.portal.chicks.index', [
            'merchant' => $merchant,
            'agents' => Auth::user()->isInternal() ? Merchant::marketplaceApproved()->orderBy('business_name')->get(['id', 'business_name']) : collect(),
            'contracts' => $contracts,
            'days' => $days->groupBy(fn ($d) => $d->hatch_date->toDateString()),
            'bookings' => $bookings,
            'chickTypes' => ChickType::active()->get(),
            'hatcheries' => Producer::active()->where(fn ($q) => $q->hatcheries()->orWhereHas('chickTypes'))->orderBy('name')->get(['id', 'name']),
            'stores' => $merchant ? Branch::where('merchant_id', $merchant->id)->where('status', Branch::STATUS_ACTIVE)->orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'is_marketplace_enabled']) : collect(),
            'boxSize' => HatchSchedule::boxSize(),
            'weekdayNames' => ChickContract::WEEKDAYS,
        ]);
    }
}
