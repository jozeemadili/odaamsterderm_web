<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Today's sales, one row per (product, staff member) so an owner can see
     * not just what sold but who sold it. Profit is null for any row made up
     * entirely of sales recorded before unit_cost started being captured at
     * checkout — we never guess a historical cost.
     */
    public function todaySales(Request $request)
    {
        $merchantId = Auth::user()->merchant_id;

        $rows = DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'sale_items.inventory_item_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_items.branch_id')
            ->leftJoin('users', 'users.id', '=', 'sales_records.recorded_by')
            ->where('sales_records.merchant_id', $merchantId)
            ->whereDate('sales_records.sale_date', now()->toDateString())
            ->select([
                'sale_items.inventory_item_id',
                'sale_items.item_name as product_name',
                'sales_records.recorded_by',
                'users.name as sold_by',
                'inventory_items.branch_id',
                'branches.name as branch_name',
            ])
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.subtotal) as total_sales_amount')
            ->selectRaw('SUM(CASE WHEN sale_items.unit_cost IS NOT NULL THEN sale_items.unit_cost * sale_items.quantity ELSE 0 END) as total_buying_value_raw')
            ->selectRaw('SUM(CASE WHEN sale_items.unit_cost IS NOT NULL THEN (sale_items.unit_price - sale_items.unit_cost) * sale_items.quantity ELSE 0 END) as total_profit_raw')
            ->selectRaw('COUNT(sale_items.unit_cost) as cost_lines_count')
            ->selectRaw('COUNT(*) as total_lines_count')
            ->groupBy('sale_items.inventory_item_id', 'sale_items.item_name', 'sales_records.recorded_by', 'users.name', 'inventory_items.branch_id', 'branches.name')
            ->orderByDesc('total_sales_amount')
            ->get();

        $mapped = $rows->map(fn ($row) => [
            'inventory_item_id' => $row->inventory_item_id,
            'product_name' => $row->product_name,
            'quantity_sold' => (float) $row->quantity_sold,
            'total_sales_amount' => (float) $row->total_sales_amount,
            'total_buying_value' => $row->cost_lines_count > 0  ? (float) $row->total_buying_value_raw : null,
            'total_profit' => $row->cost_lines_count > 0  ? (float) $row->total_profit_raw : null,
            'sold_by' => $row->sold_by,
            'store' => $row->branch_name,
        ]);

        return response()->json([
            'date' => now()->toDateString(),
            'summary' => [
                'total_quantity_sold' => (float) $rows->sum('quantity_sold'),
                'total_sales_amount' => (float) $rows->sum('total_sales_amount'),
                'total_buying_value' => (float) $rows->sum('total_buying_value_raw'),
                'total_profit' => (float) $rows->sum('total_profit_raw'),
                'profit_partial' => $rows->contains(fn ($row) => $row->cost_lines_count < $row->total_lines_count),
            ],
            'rows' => $mapped,
        ]);
    }

    /**
     * Product performance, all time: ranked by units sold and, separately,
     * by profit generated. A product with no cost-captured sale lines at all
     * can't be ranked for profit, so it's left out of that list rather than
     * shown with a misleading zero.
     */
    public function productPerformance(Request $request)
    {
        $merchantId = Auth::user()->merchant_id;
        $limit = $request->integer('limit', 20);

        $base = fn () => DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'sale_items.inventory_item_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_items.branch_id')
            ->where('sales_records.merchant_id', $merchantId)
            ->select([
                'sale_items.inventory_item_id',
                'sale_items.item_name as product_name',
                'inventory_items.branch_id',
                'branches.name as branch_name',
            ])
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.subtotal) as total_sales_amount')
            ->selectRaw('SUM(CASE WHEN sale_items.unit_cost IS NOT NULL THEN (sale_items.unit_price - sale_items.unit_cost) * sale_items.quantity ELSE 0 END) as total_profit_raw')
            ->selectRaw('COUNT(sale_items.unit_cost) as cost_lines_count')
            ->groupBy('sale_items.inventory_item_id', 'sale_items.item_name', 'inventory_items.branch_id', 'branches.name');

        $mostSold = $base()->orderByDesc('quantity_sold')->limit($limit)->get();
        $mostProfit = $base()->havingRaw('COUNT(sale_items.unit_cost) > 0')->orderByDesc('total_profit_raw')->limit($limit)->get();

        $mapRow = fn ($row) => [
            'inventory_item_id' => $row->inventory_item_id,
            'product_name' => $row->product_name,
            'quantity_sold' => (float) $row->quantity_sold,
            'total_sales_amount' => (float) $row->total_sales_amount,
            'total_profit' => $row->cost_lines_count > 0  ? (float) $row->total_profit_raw : null,
            'store' => $row->branch_name,
        ];

        return response()->json([
            'most_sold' => $mostSold->map($mapRow),
            'most_profitable' => $mostProfit->map($mapRow),
        ]);
    }
}
