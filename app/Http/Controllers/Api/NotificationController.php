<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DamageReportResource;
use App\Http\Resources\InventoryItemResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $merchant = $request->user()->merchant;

        $lowStock = $merchant->lowStockItems()->with(['category', 'branch'])->limit(10)->get();
        $expiring = $merchant->expiringSoonItems()->with(['category', 'branch'])->orderBy('expiry_date')->limit(10)->get();
        $damaged = $merchant->damageReports()->with(['inventoryItem', 'branch', 'reportedBy'])->latest('reported_at')->limit(10)->get();

        return response()->json([
            'count' => $merchant->lowStockItems()->count()
                + $merchant->expiringSoonItems()->count()
                + $merchant->damageReports()->count(),
            'low_stock_items' => InventoryItemResource::collection($lowStock),
            'expiring_soon_items' => InventoryItemResource::collection($expiring),
            'damaged_items' => DamageReportResource::collection($damaged),
        ]);
    }
}
