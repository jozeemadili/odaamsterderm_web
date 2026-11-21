<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\OrderResource;
use App\Models\HatchSchedule;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Services\CareSuggestions;
use App\Services\ChickService;
use App\Support\GeoPoint;
use Illuminate\Http\Request;

/** Day-old chicks for customers: types, agents and order days, and booking boxes. */
class ChickController extends Controller
{
    public function __construct(private readonly ChickService $chicks) {}

    /** Everything the chick pages need, in one call (public). Pass lat/lng for nearest agents first. */
    public function index(Request $request)
    {
        return response()->json([
            'box_size' => HatchSchedule::boxSize(),
            'show_prices' => PlatformSetting::showPrices(),
            'types' => $this->chicks->catalogue(GeoPoint::tryFrom($request->input('lat'), $request->input('lng'))),
        ]);
    }

    /**
     * What the chicks being booked will need in their first days, sized to
     * the boxes and matched to the pickup store's shelf (at most three).
     */
    public function suggestions(Request $request, CareSuggestions $care)
    {
        $data = $request->validate([
            'day' => ['required', 'integer'],
            'boxes' => ['required', 'integer', 'min:1', 'max:1000'],
            'store_id' => ['required', 'integer'],
        ]);
        $day = HatchSchedule::with('chickType')->findOrFail($data['day']);

        return response()->json(['data' => $care->forBooking($day, (int) $data['boxes'], (int) $data['store_id'])]);
    }

    /** After booking without add-ons: what the chicks will still need, from the same store. */
    public function orderSuggestions(Request $request, Order $order, CareSuggestions $care)
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $line = $order->items()->whereNotNull('hatch_schedule_id')->with('hatchSchedule.chickType')->first();
        if (! $line?->hatchSchedule || ! in_array($order->status, Order::OPEN, true) || $order->items()->where('source', 'care_plan')->exists()) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $care->forBooking(
            $line->hatchSchedule,
            intdiv((int) $line->quantity, HatchSchedule::boxSize()),
            $order->branch_id,
            $order->items()->pluck('product_id')->filter()->all(),
        )]);
    }

    /** A signed-in customer books whole boxes on an order day. */
    public function order(Request $request)
    {
        $data = $request->validate([
            'hatch_schedule_id' => ['required', 'integer'],
            'boxes' => ['required', 'integer', 'min:1', 'max:1000'],
            'store_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
            'extras' => ['nullable', 'array', 'max:5'],
            'extras.*.offer_id' => ['required', 'integer'],
            'extras.*.quantity' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        $order = $this->chicks->placeOrder(
            $request->user(), (int) $data['hatch_schedule_id'], (int) $data['boxes'], (int) $data['store_id'],
            $data['phone'] ?? null, $data['note'] ?? null,
            collect($data['extras'] ?? [])->mapWithKeys(fn ($e) => [(int) $e['offer_id'] => (float) $e['quantity']])->all(),
        );

        return (new OrderResource($order->load(['items.hatchSchedule', 'branch.merchant', 'merchant', 'events'])))
            ->response()->setStatusCode(201);
    }
}
