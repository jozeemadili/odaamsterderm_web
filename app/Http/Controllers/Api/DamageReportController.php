<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DamageReportResource;
use App\Models\DamageReport;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DamageReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = DamageReport::where('merchant_id', Auth::user()->merchant_id)
            ->with(['inventoryItem', 'branch', 'reportedBy'])
            ->latest('reported_at')
            ->limit($request->integer('limit', 50))
            ->get();

        return DamageReportResource::collection($reports);
    }

    /**
     * Report a damaged/wasted product. The photo is optional — a merchant may
     * just want the quantity and a note recorded quickly at the counter.
     */
    public function store(Request $request)
    {
        $merchantId = Auth::user()->merchant_id;

        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $item = InventoryItem::where('merchant_id', $merchantId)->findOrFail($data['inventory_item_id']);

        if ((float) $data['quantity'] > (float) $item->quantity_on_hand) {
            return response()->json([
                'message' => "Not enough stock for {$item->name} ({$item->quantity_on_hand} {$item->unit} left).",
            ], 422);
        }

        $report = DB::transaction(function () use ($merchantId, $data, $item, $request) {
            $report = DamageReport::create([
                'merchant_id' => $merchantId,
                'inventory_item_id' => $item->id,
                'branch_id' => $item->branch_id,
                'quantity' => $data['quantity'],
                'description' => $data['description'] ?? null,
                'reported_by' => Auth::id(),
                'reported_at' => now()->toDateString(),
            ]);

            if ($request->hasFile('photo')) {
                $report->addMedia($request->file('photo')->getRealPath())
                    ->usingFileName($request->file('photo')->getClientOriginalName())
                    ->toMediaCollection('photo');
            }

            StockMovement::create([
                'merchant_id' => $merchantId,
                'inventory_item_id' => $item->id,
                'type' => StockMovement::TYPE_DAMAGE,
                'quantity' => $data['quantity'],
                'reference' => 'damage_report',
                'notes' => "Damage report #{$report->id}",
                'movement_date' => now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);

            $item->decrement('quantity_on_hand', $data['quantity']);

            return $report;
        });

        return new DamageReportResource($report->load(['inventoryItem', 'branch', 'reportedBy']));
    }
}
