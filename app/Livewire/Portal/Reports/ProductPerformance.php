<?php

namespace App\Livewire\Portal\Reports;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * All-time product rankings by units sold and by profit. Mirrors the API's
 * ReportController::productPerformance() so the portal and mobile app agree
 * on what "best selling" and "most profitable" mean.
 */
#[Layout('layouts.portal', ['title' => 'Product Performance'])]
class ProductPerformance extends Component
{
    public string $tab = 'most_sold';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['most_sold', 'most_profitable'], true)) {
            $this->tab = $tab;
        }
    }

    protected function baseQuery()
    {
        $merchantId = Auth::user()->merchant_id;

        return DB::table('sale_items')
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
    }

    public function render()
    {
        $limit = 20;

        $mostSold = $this->baseQuery()->orderByDesc('quantity_sold')->limit($limit)->get();
        $mostProfitable = $this->baseQuery()->havingRaw('COUNT(sale_items.unit_cost) > 0')->orderByDesc('total_profit_raw')->limit($limit)->get();

        return view('livewire.portal.reports.product-performance', [
            'mostSold' => $mostSold,
            'mostProfitable' => $mostProfitable,
        ]);
    }
}
