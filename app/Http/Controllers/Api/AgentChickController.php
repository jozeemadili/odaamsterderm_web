<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\OrderItem;
use App\Models\Producer;
use App\Services\ChickService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** An agent's day-old chick sales: hatchery contracts and order days. */
class AgentChickController extends Controller
{
    public function __construct(private readonly ChickService $chicks) {}

    /** Contracts, upcoming order days and what the forms need, in one call. */
    public function index(Request $request)
    {
        $merchant = $request->user()->merchant;
        abort_unless($merchant?->canSellOnMarketplace(), 403, 'Your business must be approved for the marketplace to sell chicks.');
        $this->chicks->ensureUpcoming();

        $stores = Branch::where('merchant_id', $merchant->id)->where('status', Branch::STATUS_ACTIVE)->orderByDesc('is_primary')->orderBy('name')->get();

        return response()->json([
            'box_size' => HatchSchedule::boxSize(),
            'contracts' => ChickContract::where('merchant_id', $merchant->id)->with(['producer', 'chickType'])->get()
                ->map(fn (ChickContract $c) => $this->contractArray($c))->values(),
            'days' => HatchSchedule::where('merchant_id', $merchant->id)->withBooked()->with(['producer', 'chickType'])
                ->whereDate('hatch_date', '>=', today())->orderBy('hatch_date')->limit(150)->get()
                ->map(fn (HatchSchedule $d) => $this->dayArray($d))->values(),
            'hatcheries' => Producer::active()->where(fn ($q) => $q->hatcheries()->orWhereHas('chickTypes'))->orderBy('name')->get(['id', 'name']),
            'chick_types' => ChickType::active()->get(['id', 'code', 'name', 'name_sw']),
            'stores' => $stores->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name, 'on_marketplace' => (bool) $b->is_marketplace_enabled])->values(),
            'weekdays' => ChickContract::WEEKDAYS,
        ]);
    }

    public function storeContract(Request $request)
    {
        return response()->json($this->contractArray($this->save($request)), 201);
    }

    public function updateContract(Request $request, ChickContract $contract)
    {
        abort_unless($contract->merchant_id === $request->user()->merchant_id, 404);

        return response()->json($this->contractArray($this->save($request, $contract)));
    }

    /** An extra order day. */
    public function storeDay(Request $request)
    {
        $data = $this->validateDay($request, true);
        $contract = ChickContract::where('merchant_id', $request->user()->merchant_id)->findOrFail($data['chick_contract_id']);

        return response()->json($this->dayArray($this->chicks->saveOrderDay($contract, $data)->load(['producer', 'chickType'])), 201);
    }

    /** Change a day's cap, price or note, or close / reopen it. */
    public function updateDay(Request $request, HatchSchedule $day)
    {
        abort_unless($day->merchant_id === $request->user()->merchant_id, 404);

        if ($request->has('is_closed') && ! $request->hasAny(['cap_boxes', 'price_per_chick', 'notes'])) {
            $day->update(['is_closed' => $request->boolean('is_closed')]);
        } else {
            $data = $this->validateDay($request, false);
            $this->chicks->saveOrderDay($day->contract, $data + ['hatch_date' => $day->hatch_date->toDateString()], $day);
            if ($request->has('is_closed')) {
                $day->update(['is_closed' => $request->boolean('is_closed')]);
            }
        }

        return response()->json($this->dayArray($day->fresh()->load(['producer', 'chickType'])));
    }

    public function destroyDay(Request $request, HatchSchedule $day)
    {
        abort_unless($day->merchant_id === $request->user()->merchant_id, 404);
        abort_if($day->orderItems()->exists(), 422, 'This day has bookings; close it instead.');
        $day->delete();

        return response()->noContent();
    }

    public function bookings(Request $request, HatchSchedule $day)
    {
        abort_unless($day->merchant_id === $request->user()->merchant_id, 404);

        return response()->json(OrderItem::where('hatch_schedule_id', $day->id)->with('order.branch')->latest()->get()
            ->map(fn (OrderItem $i) => [
                'order_id' => $i->order_id,
                'reference' => $i->order->reference,
                'status' => $i->order->status,
                'status_label' => $i->order->statusLabel(),
                'customer_name' => $i->order->customer_name,
                'customer_phone' => $i->order->customer_phone,
                'boxes' => intdiv((int) $i->quantity, HatchSchedule::boxSize()),
                'store' => $i->order->branch?->name,
            ])->values());
    }

    private function save(Request $request, ?ChickContract $contract = null): ChickContract
    {
        $data = $request->validate([
            'producer_id' => ['nullable', 'integer', Rule::exists('producers', 'id')],
            'new_hatchery' => ['nullable', 'string', 'max:255', 'required_without:producer_id'],
            'chick_type_id' => ['required', 'integer', Rule::exists('chick_types', 'id')],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'cap_boxes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'price_per_chick' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($data['producer_id'])) {
            $name = trim($data['new_hatchery']);
            $data['producer_id'] = Producer::whereRaw('LOWER(name) = ?', [Str::lower($name)])->value('id')
                ?? Producer::create(['name' => $name, 'slug' => $this->slug($name), 'role' => 'Hatchery (day-old chicks)', 'deals_in' => Producer::DEALS_CHICKS, 'country' => 'Tanzania', 'is_active' => true])->id;
        }

        return $this->chicks->saveContract($request->user()->merchant, [
            'price_per_chick' => isset($data['price_per_chick']) ? (string) $data['price_per_chick'] : '',
            'is_active' => $data['is_active'] ?? true,
        ] + $data, $contract);
    }

    private function validateDay(Request $request, bool $new): array
    {
        return $request->validate([
            'chick_contract_id' => [$new ? 'required' : 'nullable', 'integer'],
            'hatch_date' => [$new ? 'required' : 'nullable', 'date'],
            'cap_boxes' => ['required', 'integer', 'min:1', 'max:100000'],
            'price_per_chick' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'hatchery';
        $slug = $base;
        for ($i = 2; Producer::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function contractArray(ChickContract $c): array
    {
        $c->loadMissing(['producer', 'chickType']);

        return [
            'id' => $c->id,
            'producer' => ['id' => $c->producer->id, 'name' => $c->producer->name],
            'chick_type' => ['id' => $c->chickType->id, 'code' => $c->chickType->code, 'name' => $c->chickType->name],
            'branch_ids' => $c->branch_ids ?? [],
            'weekdays' => $c->weekdays,
            'cap_boxes' => $c->cap_boxes,
            'price_per_chick' => $c->price_per_chick !== null ? (float) $c->price_per_chick : null,
            'is_active' => $c->is_active,
            'is_sample' => $c->is_sample,
        ];
    }

    private function dayArray(HatchSchedule $d): array
    {
        return [
            'id' => $d->id,
            'chick_contract_id' => $d->chick_contract_id,
            'hatch_date' => $d->hatch_date->toDateString(),
            'producer' => $d->producer->name,
            'chick_type' => $d->chickType->name,
            'cap_boxes' => $d->capBoxes(),
            'booked_boxes' => intdiv($d->booked(), HatchSchedule::boxSize()),
            'boxes_available' => $d->boxesAvailable(),
            'price_per_chick' => $d->price_per_chick !== null ? (float) $d->price_per_chick : null,
            'status' => $d->status(),
            'status_label' => $d->statusLabel(),
            'is_closed' => $d->is_closed,
            'notes' => $d->notes,
        ];
    }
}
