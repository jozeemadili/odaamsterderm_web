<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesRecordResource;
use App\Models\SalesRecord;
use App\Support\StoreAccess;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $sales = SalesRecord::forUser()
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('sale_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('sale_date', '<=', $request->date('date_to')))
            ->with('customer')
            ->latest('sale_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return SalesRecordResource::collection($sales);
    }

    public function show(Request $request, SalesRecord $sale)
    {
        abort_unless($sale->merchant_id === $request->user()->merchant_id && StoreAccess::allows($sale->branch_id, $request->user()), 403);

        return new SalesRecordResource($sale->load(['items', 'customer']));
    }
}
