<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\OrderResource;
use App\Models\HatchSchedule;
use App\Models\PlatformSetting;
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

    /** A signed-in customer books whole boxes on an order day. */
    public function order(Request $request)
    {
        $data = $request->validate([
            'hatch_schedule_id' => ['required', 'integer'],
            'boxes' => ['required', 'integer', 'min:1', 'max:1000'],
            'store_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $this->chicks->placeOrder(
            $request->user(), (int) $data['hatch_schedule_id'], (int) $data['boxes'], (int) $data['store_id'],
            $data['phone'] ?? null, $data['note'] ?? null,
        );

        return (new OrderResource($order->load(['items.hatchSchedule', 'branch.merchant', 'merchant', 'events'])))
            ->response()->setStatusCode(201);
    }
}
