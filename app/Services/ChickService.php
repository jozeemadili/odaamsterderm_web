<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Support\GeoPoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Day-old chicks, sold by agents. Each agent declares contracts with
 * hatcheries (hatchery + chick type + pickup stores + weekdays + cap);
 * order days are generated from them (Monday and Thursday by default) a few
 * weeks ahead. Customers pick a type, an agent near them and an order day,
 * and book whole boxes; the booking is an ordinary order to that agent
 * whose line points at the order day instead of shop stock.
 */
class ChickService
{
    /** Catalogue category of day-old chicks and hatching eggs. */
    public const CATEGORY = 'C7010';

    /**
     * Whether a user manages chicks: staff of an agent that may sell on the
     * marketplace (their own agent), or internal staff (every agent).
     */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isInternal() || ($user->merchant_id !== null && (bool) $user->merchant?->canSellOnMarketplace());
    }

    /**
     * The platform's own agent (Amsterdam Group): the one with the configured
     * code, or else the approved seller with that name and live stores. Used
     * for the sample data and the move from the single-seller setup.
     */
    public function platformAgent(): ?Merchant
    {
        return once(fn () => Merchant::where('code', config('hub.chick_seller'))->first()
            ?? Merchant::where('business_name', config('hub.chick_seller_name'))
                ->where('kyc_status', Merchant::KYC_APPROVED)
                ->whereHas('branches', fn ($q) => $q->where('is_marketplace_enabled', true))
                ->withCount(['branches as live_stores' => fn ($q) => $q->where('is_marketplace_enabled', true)])
                ->orderByDesc('live_stores')
                ->first());
    }

    // --- Contracts and order days -----------------------------------------

    /**
     * Creates or updates an agent's contract with a hatchery for one chick
     * type, then brings its order days in line: future days take the new
     * cap and price, days on dropped weekdays without bookings go, and the
     * weeks ahead are filled in.
     */
    public function saveContract(Merchant $merchant, array $data, ?ChickContract $contract = null): ChickContract
    {
        $producer = Producer::findOrFail($data['producer_id']);
        $type = ChickType::findOrFail($data['chick_type_id']);
        $weekdays = collect($data['weekdays'] ?? ChickContract::DEFAULT_WEEKDAYS)->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 1 && $d <= 7)->unique()->sort()->values()->all();
        if ($weekdays === []) {
            throw ValidationException::withMessages(['weekdays' => 'Choose at least one day the chicks arrive.']);
        }

        $storeIds = collect($data['branch_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $ownStores = Branch::where('merchant_id', $merchant->id)->pluck('id');
        if ($storeIds->diff($ownStores)->isNotEmpty()) {
            throw ValidationException::withMessages(['branch_ids' => 'Choose your own stores.']);
        }

        $duplicate = ChickContract::where('merchant_id', $merchant->id)->where('producer_id', $producer->id)
            ->where('chick_type_id', $type->id)->when($contract, fn ($q) => $q->whereKeyNot($contract->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['producer_id' => "You already sell {$producer->name} {$type->name} chicks; edit that one."]);
        }

        $contract ??= new ChickContract(['merchant_id' => $merchant->id]);
        $weekdaysChanged = $contract->exists && $contract->weekdays !== $weekdays;

        $contract->fill([
            'producer_id' => $producer->id,
            'chick_type_id' => $type->id,
            'branch_ids' => $storeIds->isEmpty() ? null : $storeIds->all(),
            'weekdays' => $weekdays,
            'cap_boxes' => max(1, (int) ($data['cap_boxes'] ?? ChickContract::DEFAULT_CAP_BOXES)),
            'price_per_chick' => ($data['price_per_chick'] ?? '') === '' ? null : (float) $data['price_per_chick'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        $producer->chickTypes()->syncWithoutDetaching([$type->id]);
        if (! $producer->makesChicks()) {
            $producer->update(['deals_in' => $producer->deals_in === Producer::DEALS_FEED ? Producer::DEALS_CHICKS_FEED : Producer::DEALS_CHICKS]);
        }

        // Future days follow the contract (never below what is already booked).
        foreach ($contract->orderDays()->withBooked()->whereDate('hatch_date', '>=', today())->get() as $day) {
            if ($weekdaysChanged && ! in_array($day->hatch_date->isoWeekday(), $weekdays, true) && $day->booked() === 0) {
                $day->delete();

                continue;
            }
            $day->update([
                'capacity' => max($contract->cap_boxes * HatchSchedule::boxSize(), (int) ceil($day->booked() / HatchSchedule::boxSize()) * HatchSchedule::boxSize()),
                'price_per_chick' => $contract->price_per_chick,
                'is_closed' => $contract->is_active ? $day->is_closed : true,
            ]);
        }
        if ($weekdaysChanged) {
            $contract->update(['generated_until' => today()->subDay()]);
        }

        $this->generateDays($contract->fresh());

        return $contract->fresh();
    }

    /**
     * Fills in order days on the contract's weekdays up to N weeks ahead.
     * Days already generated are never recreated, so a day the agent
     * deleted stays gone.
     */
    public function generateDays(ChickContract $contract, ?int $weeks = null): int
    {
        if (! $contract->is_active) {
            return 0;
        }

        $until = today()->addWeeks($weeks ?? (int) config('hub.chick_weeks_ahead', 4));
        $from = $contract->generated_until && $contract->generated_until->gte(today())
            ? $contract->generated_until->copy()->addDay()
            : today();
        $product = $this->productFor($contract->producer, $contract->chickType);
        $added = 0;

        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            if (! in_array($day->isoWeekday(), $contract->weekdays, true)) {
                continue;
            }
            $exists = HatchSchedule::where('merchant_id', $contract->merchant_id)->where('producer_id', $contract->producer_id)
                ->where('chick_type_id', $contract->chick_type_id)->whereDate('hatch_date', $day)->exists();
            if ($exists) {
                continue;
            }

            HatchSchedule::create([
                'merchant_id' => $contract->merchant_id,
                'chick_contract_id' => $contract->id,
                'producer_id' => $contract->producer_id,
                'chick_type_id' => $contract->chick_type_id,
                'product_id' => $product->id,
                'hatch_date' => $day->copy(),
                'order_cutoff_date' => $day->copy(),
                'capacity' => $contract->cap_boxes * HatchSchedule::boxSize(),
                'price_per_chick' => $contract->price_per_chick,
                'is_sample' => $contract->is_sample,
                'notes' => $contract->is_sample ? 'Sample order day' : null,
            ]);
            $added++;
        }

        if (! $contract->generated_until || $contract->generated_until->lt($until)) {
            $contract->update(['generated_until' => $until]);
        }

        return $added;
    }

    /** Keeps every active contract's order days filled ahead; runs at most once a day. */
    public function ensureUpcoming(): void
    {
        if (! Cache::add('chicks:days-generated:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        ChickContract::active()->with(['producer', 'chickType'])->each(fn (ChickContract $c) => $this->generateDays($c));
    }

    /** An extra order day (or changes to one): cap in boxes, price and a note. */
    public function saveOrderDay(ChickContract $contract, array $data, ?HatchSchedule $day = null): HatchSchedule
    {
        $date = Carbon::parse($data['hatch_date'])->startOfDay();
        if (! $day && $date->lt(today())) {
            throw ValidationException::withMessages(['hatch_date' => 'Choose today or a later day.']);
        }

        $duplicate = HatchSchedule::where('merchant_id', $contract->merchant_id)->where('producer_id', $contract->producer_id)
            ->where('chick_type_id', $contract->chick_type_id)->whereDate('hatch_date', $date)
            ->when($day, fn ($q) => $q->whereKeyNot($day->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['hatch_date' => "There is already an order day on {$date->format('j M Y')}."]);
        }

        $capBoxes = max(1, (int) $data['cap_boxes']);
        if ($day && $capBoxes * HatchSchedule::boxSize() < $day->booked()) {
            $booked = (int) ceil($day->booked() / HatchSchedule::boxSize());
            throw ValidationException::withMessages(['cap_boxes' => "{$booked} boxes are already booked; the cap can't be lower."]);
        }

        $day ??= new HatchSchedule([
            'merchant_id' => $contract->merchant_id,
            'chick_contract_id' => $contract->id,
            'producer_id' => $contract->producer_id,
            'chick_type_id' => $contract->chick_type_id,
            'product_id' => $this->productFor($contract->producer, $contract->chickType)->id,
        ]);
        $day->fill([
            'hatch_date' => $date,
            'order_cutoff_date' => $date,
            'capacity' => $capBoxes * HatchSchedule::boxSize(),
            'price_per_chick' => ($data['price_per_chick'] ?? '') === '' ? null : (float) $data['price_per_chick'],
            'notes' => ($data['notes'] ?? '') ?: null,
        ])->save();

        return $day;
    }

    // --- Customers ---------------------------------------------------------

    /**
     * Everything the customer pages need: each active chick type with the
     * agents selling it (nearest first when a location is known), and each
     * agent's hatcheries with their bookable order days.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(?GeoPoint $from = null): array
    {
        $this->ensureUpcoming();
        $withPrice = PlatformSetting::showPrices();

        $days = HatchSchedule::query()
            ->bookable()
            ->withBooked()
            ->whereNotNull('merchant_id')
            ->whereHas('merchant', fn ($q) => $q->marketplaceApproved())
            ->whereHas('producer', fn ($q) => $q->active())
            ->with(['producer.media', 'merchant.media', 'contract'])
            ->orderBy('hatch_date')
            ->get()
            ->groupBy('chick_type_id');

        $stores = [];
        $storesFor = function (HatchSchedule $day) use (&$stores, $from) {
            $key = $day->chick_contract_id ?? 'm'.$day->merchant_id;

            return $stores[$key] ??= ($day->contract?->pickupStores()
                ?? Branch::where('merchant_id', $day->merchant_id)->where('status', Branch::STATUS_ACTIVE)->where('is_marketplace_enabled', true)->get())
                ->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'address' => collect([$b->address, $b->area, $b->city])->filter()->unique()->join(', '),
                    'distance_km' => $from && $b->latitude !== null && $b->longitude !== null ? round($from->distanceTo((float) $b->latitude, (float) $b->longitude), 1) : null,
                ])->sortBy(fn ($s) => $s['distance_km'] ?? INF)->values()->all();
        };

        return ChickType::active()->get()->map(function (ChickType $type) use ($days, $withPrice, $storesFor) {
            $agents = ($days[$type->id] ?? collect())
                ->groupBy('merchant_id')
                ->map(function (Collection $agentDays) use ($withPrice, $storesFor) {
                    $merchant = $agentDays->first()->merchant;
                    $hatcheries = $agentDays->groupBy('producer_id')->map(function (Collection $hDays) use ($withPrice, $storesFor) {
                        $producer = $hDays->first()->producer;

                        return [
                            'id' => $producer->id,
                            'name' => $producer->name,
                            'slug' => $producer->slug,
                            'logo_url' => $producer->logoUrl(),
                            'location' => $producer->location,
                            'description' => $producer->description,
                            'boxes_available' => $hDays->sum(fn (HatchSchedule $s) => $s->boxesAvailable()),
                            'pickup_stores' => $storesFor($hDays->first()),
                            'dates' => $hDays->map(fn (HatchSchedule $s) => $s->summaryArray($withPrice))->values()->all(),
                        ];
                    })->values();

                    $stores = $hatcheries->flatMap(fn ($h) => $h['pickup_stores'])->unique('id')->sortBy(fn ($s) => $s['distance_km'] ?? INF)->values();

                    return [
                        'id' => $merchant->id,
                        'name' => $merchant->business_name,
                        'logo_url' => $merchant->getFirstMediaUrl('logo') ?: null,
                        'nearest_km' => $stores->first()['distance_km'] ?? null,
                        'nearest_store' => $stores->first()['name'] ?? null,
                        'stores_count' => $stores->count(),
                        'boxes_available' => $hatcheries->sum('boxes_available'),
                        'next_date' => $agentDays->first(fn (HatchSchedule $s) => $s->boxesAvailable() > 0)?->hatch_date->toDateString(),
                        'hatcheries' => $hatcheries->all(),
                    ];
                })
                ->sortBy([fn ($a, $b) => ($a['nearest_km'] ?? INF) <=> ($b['nearest_km'] ?? INF), ['next_date', 'asc'], ['name', 'asc']])
                ->values()
                ->all();

            return $type->summaryArray() + [
                'agents' => $agents,
                'agents_count' => count($agents),
                'boxes_available' => array_sum(array_column($agents, 'boxes_available')),
            ];
        })->all();
    }

    /**
     * Books whole boxes of chicks on an order day, for pickup at one of the
     * agent's stores for that contract. The day row is locked while the free
     * boxes are counted, so two customers can't book the same last box.
     * Suggested products the customer added ($extras: offer id => packs)
     * join the same order and their stock is reserved, so everything is
     * collected together.
     *
     * @param  array<int, int|float>  $extras
     */
    public function placeOrder(User $customer, int $scheduleId, int $boxes, int $storeId, ?string $phone = null, ?string $note = null, array $extras = []): Order
    {
        if ($boxes < 1) {
            throw ValidationException::withMessages(['boxes' => 'Book at least one box.']);
        }

        $order = DB::transaction(function () use ($customer, $scheduleId, $boxes, $storeId, $phone, $note, $extras) {
            /** @var HatchSchedule|null $schedule */
            $schedule = HatchSchedule::with(['producer', 'chickType', 'contract', 'merchant'])->lockForUpdate()->find($scheduleId);

            if (! $schedule || ! $schedule->merchant_id || in_array($schedule->status(), [HatchSchedule::PAST, HatchSchedule::CLOSED], true)) {
                throw ValidationException::withMessages(['hatch_date' => 'Booking for this day has closed. Choose another day.']);
            }

            $stores = $schedule->contract?->pickupStores()
                ?? Branch::where('merchant_id', $schedule->merchant_id)->where('status', Branch::STATUS_ACTIVE)->where('is_marketplace_enabled', true)->get();
            $store = $stores->firstWhere('id', $storeId)
                ?? throw ValidationException::withMessages(['store' => 'Choose one of the stores where these chicks arrive.']);

            $free = $schedule->boxesAvailable();
            if ($boxes > $free) {
                throw ValidationException::withMessages(['boxes' => $free === 0
                    ? 'This day is full. Choose another day.'
                    : "Only {$free} ".Str::plural('box', $free).' left for this day.']);
            }

            $product = $schedule->product ?? $this->productFor($schedule->producer, $schedule->chickType);
            $schedule->product_id ??= $product->id;
            $schedule->save();

            $chicks = $boxes * HatchSchedule::boxSize();
            $price = (float) ($schedule->price_per_chick ?? 0);

            $order = Order::create([
                'reference' => Order::newReference(),
                'user_id' => $customer->id,
                'merchant_id' => $schedule->merchant_id,
                'branch_id' => $store->id,
                'status' => Order::PENDING,
                'customer_name' => $customer->name,
                'customer_phone' => $phone ?: $customer->phone,
                'customer_note' => $note ?: null,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'hatch_schedule_id' => $schedule->id,
                'name' => "{$schedule->chickType->name} day-old chicks · {$schedule->producer->name} · arrive {$schedule->hatch_date->format('D j M Y')}",
                'unit' => 'chick',
                'unit_price' => $price,
                'quantity' => $chicks,
                'line_total' => $chicks * $price,
            ]);

            $total = $chicks * $price;
            $count = 1;
            foreach ($extras as $offerId => $packs) {
                $packs = (float) $packs;
                if ($packs <= 0) {
                    continue;
                }
                $offer = InventoryItem::with(['product', 'variant'])->find((int) $offerId);
                if (! $offer || $offer->branch_id !== $store->id) {
                    throw ValidationException::withMessages(['extras' => 'One of the added products is not at this store.']);
                }
                try {
                    app(InventoryService::class)->reserve($offer, $packs, [
                        'reference' => 'marketplace_order',
                        'notes' => "Order {$order->reference}",
                        'recorded_by' => $customer->id,
                    ]);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(['extras' => collect($e->errors())->flatten()->first()]);
                }
                $line = $packs * (float) $offer->unit_price;
                $order->items()->create([
                    'inventory_item_id' => $offer->id,
                    'product_id' => $offer->product_id,
                    'source' => 'care_plan',
                    'name' => $offer->offerName(),
                    'unit' => $offer->sellingUnit(),
                    'unit_price' => (float) $offer->unit_price,
                    'quantity' => $packs,
                    'line_total' => $line,
                ]);
                $total += $line;
                $count++;
            }

            $order->update(['total' => $total, 'items_count' => $count]);
            $order->events()->create([
                'status' => Order::PENDING,
                'note' => "Chicks booked: {$boxes} ".Str::plural('box', $boxes)." ({$chicks} chicks), arriving {$schedule->hatch_date->toDateString()}",
                'user_id' => $customer->id,
                'created_at' => now(),
            ]);

            return $order;
        });

        $order->load('branch');
        Notification::send(
            User::where('merchant_id', $order->merchant_id)->where('user_type', User::TYPE_MERCHANT)->get(),
            new OrderPlaced($order),
        );

        return $order;
    }

    // --- Catalogue products ------------------------------------------------

    /**
     * The catalogue product for one hatchery's chick type ("Interchick
     * Tanbro Day-old Chicks"): the existing one, or a new one in the day-old
     * chicks category sold per bird.
     */
    public function productFor(Producer $producer, ChickType $type): Product
    {
        $existing = Product::where('producer_id', $producer->id)->where('chick_type_id', $type->id)->first();
        if ($existing) {
            return $existing;
        }

        $category = ProductCategory::where('code', self::CATEGORY)->firstOrFail();
        $chicken = AnimalType::where('code', 'AN-CHK')->firstOrFail();

        $product = Product::create([
            'name' => "{$producer->name} {$type->name} Day-old Chicks",
            'slug' => Product::uniqueSlug("{$producer->name} {$type->name} day-old chicks"),
            'producer_id' => $producer->id,
            'chick_type_id' => $type->id,
            'brand' => $producer->name,
            'product_category_id' => $category->id,
            'animal_type_id' => $chicken->id,
            'form' => 'live',
            'unit' => 'bird',
            'description' => $type->summary,
            'use_cases' => ['brooding'],
            'status' => Product::STATUS_APPROVED,
            'reviewed_at' => now(),
            'search_keywords' => Str::lower("vifaranga|{$type->name}|{$producer->name}|day old chicks"),
        ]);
        $product->animalTypes()->sync([$chicken->id]);
        if ($stage = LifeStage::where('code', 'LS-POU-STR')->value('id')) {
            $product->lifeStages()->sync([$stage]);
        }

        return $product;
    }

    /**
     * Links the catalogue's day-old chick products to their chick type
     * ("… Layer Day-old Chicks" → Layers) and marks their makers as
     * hatcheries producing that type. Safe to re-run.
     *
     * @return array{products: int, hatcheries: int}
     */
    public function linkCatalogue(): array
    {
        $types = ChickType::all()->keyBy('code');
        $words = ['broiler' => 'broiler', 'layer' => 'layers', 'sasso' => 'sasso', 'tanbro' => 'tanbro'];
        $linked = 0;
        $hatcheries = [];

        $products = Product::whereRelation('category', 'code', self::CATEGORY)
            ->where('name', 'like', '%day-old chick%')
            ->whereNotNull('producer_id')
            ->with('producer')
            ->get();

        foreach ($products as $product) {
            $code = collect($words)->first(fn ($code, $word) => str_contains(Str::lower($product->name), $word));
            $type = $code ? $types[$code] ?? null : null;
            if (! $type) {
                continue;
            }

            if ($product->chick_type_id !== $type->id) {
                $product->update(['chick_type_id' => $type->id]);
            }
            $linked++;

            $producer = $product->producer;
            $producer->chickTypes()->syncWithoutDetaching([$type->id]);
            if ($producer->deals_in === null) {
                $makesOther = $producer->products()->whereDoesntHave('category', fn ($q) => $q->where('code', 'like', 'C70%'))->exists();
                $producer->update(['deals_in' => $makesOther ? Producer::DEALS_CHICKS_FEED : Producer::DEALS_CHICKS]);
            }
            $hatcheries[$producer->id] = true;
        }

        return ['products' => $linked, 'hatcheries' => count($hatcheries)];
    }

    /**
     * Moves hatch dates from the earlier single-seller setup to agents:
     * unbooked samples are removed; any other date goes to the platform's
     * agent under a contract for its hatchery and type. Safe to re-run.
     *
     * @return array{removed: int, moved: int, contracts: int}
     */
    public function moveToAgents(): array
    {
        $removed = HatchSchedule::whereNull('merchant_id')->where('is_sample', true)->whereDoesntHave('orderItems')->delete();
        $agent = $this->platformAgent();
        $moved = 0;
        $contracts = [];

        if ($agent) {
            foreach (HatchSchedule::whereNull('merchant_id')->get() as $day) {
                $contract = ChickContract::firstOrCreate(
                    ['merchant_id' => $agent->id, 'producer_id' => $day->producer_id, 'chick_type_id' => $day->chick_type_id],
                    ['weekdays' => ChickContract::DEFAULT_WEEKDAYS, 'cap_boxes' => ChickContract::DEFAULT_CAP_BOXES, 'price_per_chick' => $day->price_per_chick, 'generated_until' => today()->subDay()],
                );
                $contracts[$contract->id] = true;
                $day->update(['merchant_id' => $agent->id, 'chick_contract_id' => $contract->id, 'order_cutoff_date' => $day->hatch_date]);
                $moved++;
            }
        }

        return ['removed' => $removed, 'moved' => $moved, 'contracts' => count($contracts)];
    }

    /** What the AI assistants know about day-old chicks: types, agents and open order days. */
    public function promptSection(): string
    {
        $types = collect($this->catalogue());
        if ($types->isEmpty()) {
            return '';
        }

        $box = HatchSchedule::boxSize();
        $typeLines = $types->map(fn ($t) => "- {$t['name']} ({$t['name_sw']}): {$t['summary']}")->implode("\n");
        $dayLines = $types->flatMap(fn ($t) => collect($t['agents'])->flatMap(fn ($a) => collect($a['hatcheries'])->flatMap(fn ($h) => collect($h['dates'])
            ->filter(fn ($d) => $d['status'] === HatchSchedule::OPEN)
            ->map(fn ($d) => "{$t['name']} | {$a['name']} | {$h['name']} | arrive {$d['hatch_date']} | {$d['boxes_available']} boxes left | collect at: ".collect($h['pickup_stores'])->pluck('name')->join(', ')))))
            ->take(60)->implode("\n");

        return <<<CHICKS
        Day-old chicks (vifaranga): agents on the marketplace sell chicks from the hatcheries they work with. Customers book them on the "Day-old chicks / Vifaranga" page of the website or app: choose the type, an agent near them and the day the chicks arrive, then book whole boxes of {$box} chicks and collect them at that agent's store on that day. Booking stays open until a day is full or has passed. Chick bookings never go in your basket; send customers to that page and tell them which agents and days suit them. Round chick numbers up to whole boxes of {$box}.
        Chick types:
        {$typeLines}
        Open order days (type | agent | hatchery | day chicks arrive | boxes left | stores):
        {$this->orNone($dayLines)}
        CHICKS;
    }

    private function orNone(string $lines): string
    {
        return $lines !== '' ? $lines : '(no order days open right now)';
    }
}
