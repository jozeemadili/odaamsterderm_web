<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\OrderResource;
use App\Models\Branch;
use App\Models\HatchSchedule;
use App\Models\PlatformSetting;
use App\Services\ChickService;
use Illuminate\Http\Request;

/** Day-old chicks: types, hatcheries and hatch dates, and booking boxes. */
class ChickController extends Controller
{
    public function __construct(private readonly ChickService $chicks) {}

    /** Everything the chick pages need, in one call (public). */
    public function index()
    {
        return response()->json([
            'box_size' => HatchSchedule::boxSize(),
            'order_cutoff_days' => HatchSchedule::cutoffDays(),
            'show_prices' => PlatformSetting::showPrices(),
            'seller' => $this->chicks->seller()?->business_name,
            'types' => $this->chicks->catalogue(),
            'pickup_stores' => $this->chicks->pickupStores()->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'address' => collect([$b->address, $b->area, $b->city])->filter()->unique()->join(', '),
                'phone' => $b->phone,
                'latitude' => $b->latitude !== null ? (float) $b->latitude : null,
                'longitude' => $b->longitude !== null ? (float) $b->longitude : null,
            ])->values(),
        ]);
    }

    /** A signed-in customer books whole boxes against a hatch date. */
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
