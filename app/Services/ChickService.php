<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\Branch;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\LifeStage;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Day-old chicks: one seller (Amsterdam Group) sells every hatchery's
 * chicks. It publishes hatch dates per hatchery and chick type; customers
 * pick a type, a hatchery and a date, and book whole boxes for pickup at
 * one of the seller's stores. The booking is an ordinary marketplace order
 * whose line points at the hatch date instead of shop stock.
 */
class ChickService
{
    /** Catalogue category of day-old chicks and hatching eggs. */
    public const CATEGORY = 'C7010';

    /**
     * The seller that sells and manages chicks: the one with the configured
     * code, or else the approved seller with that business name and live
     * stores (sellers created in the portal have no code). Null if neither.
     */
    public function seller(): ?Merchant
    {
        return once(fn () => Merchant::where('code', config('hub.chick_seller'))->first()
            ?? Merchant::where('business_name', config('hub.chick_seller_name'))
                ->where('kyc_status', Merchant::KYC_APPROVED)
                ->whereHas('branches', fn ($q) => $q->where('is_marketplace_enabled', true))
                ->withCount(['branches as live_stores' => fn ($q) => $q->where('is_marketplace_enabled', true)])
                ->orderByDesc('live_stores')
                ->first());
    }

    /** Whether this user may manage hatcheries and hatch dates. */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isInternal() || ($user->merchant_id !== null && $user->merchant_id === $this->seller()?->id);
    }

    /** The seller's stores customers can collect chicks from. */
    public function pickupStores(): Collection
    {
        $seller = $this->seller();

        return $seller
            ? Branch::where('merchant_id', $seller->id)->where('status', Branch::STATUS_ACTIVE)->where('is_marketplace_enabled', true)->orderByDesc('is_primary')->orderBy('name')->get()
            : collect();
    }

    /**
     * Everything the customer pages need: each active chick type with the
     * hatcheries that make it and their bookable dates.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        $withPrice = PlatformSetting::showPrices();
        $schedules = HatchSchedule::query()
            ->bookable()
            ->withBooked()
            ->whereHas('producer', fn ($q) => $q->active()->hatcheries())
            ->with('producer.media')
            ->orderBy('hatch_date')
            ->get()
            ->groupBy('chick_type_id');

        return ChickType::active()->get()->map(function (ChickType $type) use ($schedules, $withPrice) {
            $hatcheries = ($schedules[$type->id] ?? collect())
                ->groupBy('producer_id')
                ->map(function (Collection $dates) use ($withPrice) {
                    $producer = $dates->first()->producer;

                    return [
                        'id' => $producer->id,
                        'name' => $producer->name,
                        'slug' => $producer->slug,
                        'logo_url' => $producer->logoUrl(),
                        'location' => $producer->location,
                        'description' => $producer->description,
                        'boxes_available' => $dates->sum(fn (HatchSchedule $s) => $s->boxesAvailable()),
                        'next_hatch_date' => $dates->first(fn (HatchSchedule $s) => $s->boxesAvailable() > 0)?->hatch_date->toDateString(),
                        'dates' => $dates->map(fn (HatchSchedule $s) => $s->summaryArray($withPrice))->values()->all(),
                    ];
                })
                ->sortBy([['next_hatch_date', 'asc'], ['name', 'asc']])
                ->values()
                ->all();

            return $type->summaryArray() + [
                'hatcheries' => $hatcheries,
                'hatcheries_count' => count($hatcheries),
                'boxes_available' => array_sum(array_column($hatcheries, 'boxes_available')),
            ];
        })->all();
    }

    /**
     * Books whole boxes of chicks against a hatch date, for pickup at one
     * of the seller's stores. The date row is locked while the free chicks
     * are counted, so two customers can't book the same last box.
     */
    public function placeOrder(User $customer, int $scheduleId, int $boxes, int $storeId, ?string $phone = null, ?string $note = null): Order
    {
        $store = $this->pickupStores()->firstWhere('id', $storeId)
            ?? throw ValidationException::withMessages(['store' => 'Choose one of the stores where chicks can be collected.']);

        if ($boxes < 1) {
            throw ValidationException::withMessages(['boxes' => 'Book at least one box.']);
        }

        $order = DB::transaction(function () use ($customer, $scheduleId, $boxes, $store, $phone, $note) {
            /** @var HatchSchedule|null $schedule */
            $schedule = HatchSchedule::with(['producer', 'chickType'])->lockForUpdate()->find($scheduleId);

            if (! $schedule || $schedule->status() === HatchSchedule::PAST || $schedule->status() === HatchSchedule::CLOSED) {
                throw ValidationException::withMessages(['hatch_date' => 'Booking for this hatch date has closed. Choose another date.']);
            }

            $free = $schedule->boxesAvailable();
            if ($boxes > $free) {
                throw ValidationException::withMessages(['boxes' => $free === 0
                    ? 'This hatch date is full. Choose another date.'
                    : "Only {$free} ".Str::plural('box', $free).' left for this hatch date.']);
            }

            $product = $schedule->product ?? $this->productFor($schedule->producer, $schedule->chickType);
            $schedule->product_id ??= $product->id;
            $schedule->save();

            $chicks = $boxes * HatchSchedule::boxSize();
            $price = (float) ($schedule->price_per_chick ?? 0);

            $order = Order::create([
                'reference' => Order::newReference(),
                'user_id' => $customer->id,
                'merchant_id' => $store->merchant_id,
                'branch_id' => $store->id,
                'status' => Order::PENDING,
                'customer_name' => $customer->name,
                'customer_phone' => $phone ?: $customer->phone,
                'customer_note' => $note ?: null,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'hatch_schedule_id' => $schedule->id,
                'name' => "{$schedule->chickType->name} day-old chicks · {$schedule->producer->name} · hatch {$schedule->hatch_date->format('D j M Y')}",
                'unit' => 'chick',
                'unit_price' => $price,
                'quantity' => $chicks,
                'line_total' => $chicks * $price,
            ]);

            $order->update(['total' => $chicks * $price, 'items_count' => 1]);
            $order->events()->create([
                'status' => Order::PENDING,
                'note' => "Chicks booked: {$boxes} ".Str::plural('box', $boxes)." ({$chicks} chicks), hatch {$schedule->hatch_date->toDateString()}",
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

    /** Creates or updates a hatch date; the order cut-off defaults to N days before. */
    public function saveSchedule(array $data, ?HatchSchedule $schedule = null, ?User $by = null): HatchSchedule
    {
        $producer = Producer::findOrFail($data['producer_id']);
        $type = ChickType::findOrFail($data['chick_type_id']);
        $hatch = Carbon::parse($data['hatch_date'])->startOfDay();
        $cutoff = ! empty($data['order_cutoff_date'])
            ? Carbon::parse($data['order_cutoff_date'])->startOfDay()
            : $hatch->copy()->subDays(HatchSchedule::cutoffDays());

        if ($cutoff->gt($hatch)) {
            throw ValidationException::withMessages(['order_cutoff_date' => 'The last day to order must be on or before the hatch date.']);
        }

        $duplicate = HatchSchedule::where('producer_id', $producer->id)->where('chick_type_id', $type->id)
            ->whereDate('hatch_date', $hatch)->when($schedule, fn ($q) => $q->whereKeyNot($schedule->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['hatch_date' => "{$producer->name} already has a {$type->name} hatch on {$hatch->format('j M Y')}."]);
        }

        if ($schedule && (int) $data['capacity'] < $schedule->booked()) {
            throw ValidationException::withMessages(['capacity' => "{$schedule->booked()} chicks are already booked; the number of chicks can't be lower."]);
        }

        $schedule ??= new HatchSchedule(['created_by' => $by?->id]);
        $schedule->fill([
            'producer_id' => $producer->id,
            'chick_type_id' => $type->id,
            'product_id' => $this->productFor($producer, $type)->id,
            'hatch_date' => $hatch,
            'order_cutoff_date' => $cutoff,
            'capacity' => (int) $data['capacity'],
            'price_per_chick' => ($data['price_per_chick'] ?? '') === '' ? null : (float) $data['price_per_chick'],
            'notes' => ($data['notes'] ?? '') ?: null,
        ])->save();

        // Producing this type now, whatever was ticked before.
        $producer->chickTypes()->syncWithoutDetaching([$type->id]);

        return $schedule;
    }

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

    /** What the AI assistants know about day-old chicks: types and open hatch dates. */
    public function promptSection(): string
    {
        $types = collect($this->catalogue());
        if ($types->isEmpty()) {
            return '';
        }

        $box = HatchSchedule::boxSize();
        $seller = $this->seller()?->business_name ?? 'the marketplace';
        $typeLines = $types->map(fn ($t) => "- {$t['name']} ({$t['name_sw']}): {$t['summary']}")->implode("\n");
        $dateLines = $types->flatMap(fn ($t) => collect($t['hatcheries'])->flatMap(fn ($h) => collect($h['dates'])
            ->filter(fn ($d) => $d['status'] === HatchSchedule::OPEN)
            ->map(fn ($d) => "{$t['name']} | {$h['name']} | hatch {$d['hatch_date']} | order by {$d['order_cutoff_date']} | {$d['boxes_available']} boxes left")))
            ->take(60)->implode("\n");

        return <<<CHICKS
        Day-old chicks (vifaranga): {$seller} sells chicks from several hatcheries. Customers book them on the "Day-old chicks / Vifaranga" page of the website or app: choose the type, the hatchery and a hatch date, then book whole boxes of {$box} chicks and collect them at a {$seller} store on hatch day. Chick bookings never go in your basket; send customers to that page and tell them which dates suit them. Round chick numbers up to whole boxes of {$box}.
        Chick types:
        {$typeLines}
        Open hatch dates (type | hatchery | hatch date | last day to order | boxes left):
        {$this->orNone($dateLines)}
        CHICKS;
    }

    private function orNone(string $lines): string
    {
        return $lines !== '' ? $lines : '(no hatch dates open right now)';
    }
}
