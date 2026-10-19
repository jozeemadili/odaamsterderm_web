<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Order;
use App\Support\StoreAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "what does this merchant's business look like
 * right now" — used by both the mobile API dashboard and the portal
 * dashboard so the two never drift into showing different numbers for the
 * same thing (as they once did: the portal computed its own Sales-minus-
 * Expenses figure that never accounted for paid invoices or COGS).
 */
class DashboardMetricsService
{
    public const PERIOD_LABELS = [
        'week' => 'Last 7 days',
        'month' => 'Last 30 days',
        'year' => 'This year',
    ];

    public function summary(Merchant $merchant, string $period): array
    {
        if (! array_key_exists($period, self::PERIOD_LABELS)) {
            $period = 'month';
        }

        $trend = match ($period) {
            'week' => $this->dailyTrend($merchant, 7),
            'year' => $this->monthlyTrend($merchant, 12),
            default => $this->dailyTrend($merchant, 30),
        };

        $posSales = (float) StoreAccess::limit($merchant->salesRecords())->where('sale_date', '>=', $trend['start'])->sum('amount');
        $invoiceSales = $this->paidInvoiceTotal($merchant, $trend['start']);
        $salesTotal = $posSales + $invoiceSales;
        $cogsTotal = $this->posCogs($merchant, $trend['start']) + $this->invoiceCogs($merchant, $trend['start']);
        $expensesTotal = (float) StoreAccess::limit($merchant->expenses())->where('expense_date', '>=', $trend['start'])->sum('amount');

        $today = now()->toDateString();
        $todayPosSales = (float) StoreAccess::limit($merchant->salesRecords())->where('sale_date', $today)->sum('amount');
        $todayInvoiceSales = $this->paidInvoiceTotal($merchant, $today);
        $todaySales = $todayPosSales + $todayInvoiceSales;
        $todayCogs = $this->posCogs($merchant, $today) + $this->invoiceCogs($merchant, $today);
        $todayExpenses = (float) StoreAccess::limit($merchant->expenses())->where('expense_date', $today)->sum('amount');

        return [
            'period' => $period,
            'period_label' => self::PERIOD_LABELS[$period],
            'sales_total' => $salesTotal,
            'expenses_total' => $expensesTotal,
            'profit_total' => $salesTotal - $cogsTotal - $expensesTotal,
            'today_sales_total' => $todaySales,
            'today_expenses_total' => $todayExpenses,
            'today_profit_total' => $todaySales - $todayCogs - $todayExpenses,
            'today_sales_count' => StoreAccess::limit($merchant->salesRecords())->where('sale_date', $today)->count()
                + StoreAccess::limit($merchant->invoices())->where('status', Invoice::STATUS_PAID)->whereDate('paid_at', $today)->count(),
            'low_stock_count' => StoreAccess::limit($merchant->lowStockItems())->count(),
            'expiring_soon_count' => StoreAccess::limit($merchant->expiringSoonItems())->count(),
            'unverified_payments_count' => StoreAccess::limit($merchant->paymentRecords())->where('status', 'recorded')->count(),
            'trend' => [
                'labels' => $trend['labels'],
                'sales' => $trend['sales'],
                'expenses' => $trend['expenses'],
                'profit' => $trend['profit'],
            ],
            'unpaid_today' => $this->unpaidInvoiceTotal($merchant, today: true),
            'unpaid_total' => $this->unpaidInvoiceTotal($merchant, today: false),
            'stock_summary' => $this->stockSummary($merchant),
            'marketplace' => $this->marketplaceSummary($merchant),
            'total_stores' => $merchant->branches()->count(),
        ];
    }

    /**
     * Revenue from fully-paid invoices, dated by when they were paid (not
     * issued) so it lands in the same day/period bucket a cash sale would.
     */
    public function paidInvoiceTotal(Merchant $merchant, string $sinceDate): float
    {
        return (float) StoreAccess::limit($merchant->invoices())
            ->where('status', Invoice::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $sinceDate)
            ->sum('total');
    }

    /**
     * Cost of goods sold for POS sales dated on/after $sinceDate. Lines that
     * predate unit_cost capture contribute 0, same "don't guess" policy used
     * by the sales report.
     */
    public function posCogs(Merchant $merchant, string $sinceDate): float
    {
        return (float) DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->where('sales_records.merchant_id', $merchant->id)
            ->where('sales_records.sale_date', '>=', $sinceDate)
            ->selectRaw('COALESCE(SUM(COALESCE(sale_items.unit_cost, 0) * sale_items.quantity), 0) as total')
            ->value('total');
    }

    /**
     * Cost of goods sold for invoices paid on/after $sinceDate.
     */
    public function invoiceCogs(Merchant $merchant, string $sinceDate): float
    {
        return (float) DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.merchant_id', $merchant->id)
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->whereNotNull('invoices.paid_at')
            ->whereDate('invoices.paid_at', '>=', $sinceDate)
            ->selectRaw('COALESCE(SUM(COALESCE(invoice_items.unit_cost, 0) * invoice_items.quantity), 0) as total')
            ->value('total');
    }

    /**
     * Outstanding balance across unpaid/partially-paid invoices. With
     * `today: true`, scoped to invoices issued today (matches the "unpaid
     * amount for today" figure); otherwise the running all-time total.
     */
    public function unpaidInvoiceTotal(Merchant $merchant, bool $today): float
    {
        $invoices = StoreAccess::limit($merchant->invoices())
            ->whereIn('status', [Invoice::STATUS_INVOICED, Invoice::STATUS_PARTIALLY_PAID])
            ->when($today, fn ($q) => $q->whereDate('issue_date', now()->toDateString()))
            ->get(['total', 'amount_paid']);

        return (float) $invoices->sum(fn ($invoice) => max(0, (float) $invoice->total - (float) $invoice->amount_paid));
    }

    /**
     * Merchant-wide stock valuation — buying value at cost, selling value
     * at listed price, and the expected profit if all stock on hand sold
     * at that price. A snapshot, not scoped to the selected period.
     */
    public function stockSummary(Merchant $merchant): array
    {
        $row = StoreAccess::limit($merchant->inventoryItems())
            ->selectRaw('SUM(quantity_on_hand * COALESCE(unit_cost, 0)) as buying_value')
            ->selectRaw('SUM(quantity_on_hand * COALESCE(unit_price, 0)) as selling_value')
            ->selectRaw('SUM(quantity_on_hand) as total_quantity')
            ->selectRaw('COUNT(*) as product_count')
            ->first();

        $buyingValue = (float) ($row->buying_value ?? 0);
        $sellingValue = (float) ($row->selling_value ?? 0);

        return [
            'buying_value' => $buyingValue,
            'selling_value' => $sellingValue,
            'expected_profit' => $sellingValue - $buyingValue,
            'total_quantity' => (float) ($row->total_quantity ?? 0),
            'product_count' => (int) ($row->product_count ?? 0),
        ];
    }

    /**
     * What customers can currently see from this seller, and its
     * marketplace orders by stage.
     *
     * @return array{listed_count:int, units_exposed:float, out_of_stock_count:int, hidden_count:int, approved:bool, orders: array<string,int>}
     */
    public function marketplaceSummary(Merchant $merchant): array
    {
        return [
            'approved' => $merchant->canSellOnMarketplace(),
            'listed_count' => StoreAccess::limit($merchant->inventoryItems())->onMarketplace()->count(),
            'units_exposed' => (float) StoreAccess::limit($merchant->inventoryItems())->onMarketplace()->sum('marketplace_quantity'),
            'out_of_stock_count' => StoreAccess::limit($merchant->inventoryItems())
                ->where('is_marketplace_visible', true)
                ->where('marketplace_status', InventoryItem::MARKET_OUT_OF_STOCK)
                ->count(),
            'hidden_count' => StoreAccess::limit($merchant->inventoryItems())->where('is_marketplace_visible', false)->count(),
            'orders' => [
                'pending' => StoreAccess::limit($merchant->orders())->where('status', Order::PENDING)->count(),
                'accepted' => StoreAccess::limit($merchant->orders())->whereIn('status', [Order::ACCEPTED, Order::PREPARING])->count(),
                'ready' => StoreAccess::limit($merchant->orders())->where('status', Order::READY)->count(),
                'completed' => StoreAccess::limit($merchant->orders())->where('status', Order::COMPLETED)->count(),
            ],
        ];
    }

    /**
     * Day-by-day sales/expenses for the trailing $days days (inclusive of
     * today). Used for the "week" (7) and "month" (30) periods. Profit is
     * true net profit: (POS + paid-invoice revenue) minus cost of goods
     * sold minus expenses.
     */
    public function dailyTrend(Merchant $merchant, int $days): array
    {
        $dates = collect(range($days - 1, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $start = $dates->first();

        $posSalesByDay = StoreAccess::limit($merchant->salesRecords())
            ->selectRaw('sale_date, SUM(amount) as total')
            ->where('sale_date', '>=', $start)
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $invoiceSalesByDay = StoreAccess::limit($merchant->invoices())
            ->where('status', Invoice::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as d, SUM(total) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $posCogsByDay = DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->where('sales_records.merchant_id', $merchant->id)
            ->where('sales_records.sale_date', '>=', $start)
            ->selectRaw('sales_records.sale_date as d, SUM(COALESCE(sale_items.unit_cost, 0) * sale_items.quantity) as total')
            ->groupBy('sales_records.sale_date')
            ->pluck('total', 'd');

        $invoiceCogsByDay = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.merchant_id', $merchant->id)
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->whereNotNull('invoices.paid_at')
            ->whereDate('invoices.paid_at', '>=', $start)
            ->selectRaw('DATE(invoices.paid_at) as d, SUM(COALESCE(invoice_items.unit_cost, 0) * invoice_items.quantity) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $expensesByDay = StoreAccess::limit($merchant->expenses())
            ->selectRaw('expense_date, SUM(amount) as total')
            ->where('expense_date', '>=', $start)
            ->groupBy('expense_date')
            ->pluck('total', 'expense_date');

        $sales = $dates->map(fn ($d) => (float) ($posSalesByDay[$d] ?? 0) + (float) ($invoiceSalesByDay[$d] ?? 0))->all();
        $cogs = $dates->map(fn ($d) => (float) ($posCogsByDay[$d] ?? 0) + (float) ($invoiceCogsByDay[$d] ?? 0))->all();
        $expenses = $dates->map(fn ($d) => (float) ($expensesByDay[$d] ?? 0))->all();

        return [
            'start' => $start,
            'labels' => $dates->map(fn ($d) => Carbon::parse($d)->format('d M'))->all(),
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => array_map(fn ($s, $c, $e) => $s - $c - $e, $sales, $cogs, $expenses),
        ];
    }

    /**
     * Month-by-month sales/expenses for the trailing $months calendar
     * months (inclusive of the current month). Used for the "year" period.
     * Grouped in PHP rather than a DB-specific date-format function, so it
     * works the same regardless of the underlying database driver.
     */
    public function monthlyTrend(Merchant $merchant, int $months): array
    {
        $periods = collect(range($months - 1, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $start = $periods->first();

        $sales = StoreAccess::limit($merchant->salesRecords())
            ->where('sale_date', '>=', $start)
            ->get(['sale_date', 'amount'])
            ->groupBy(fn ($row) => Carbon::parse($row->sale_date)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $invoiceSales = StoreAccess::limit($merchant->invoices())
            ->where('status', Invoice::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'total'])
            ->groupBy(fn ($row) => Carbon::parse($row->paid_at)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('total'));

        $posCogs = DB::table('sale_items')
            ->join('sales_records', 'sales_records.id', '=', 'sale_items.sale_id')
            ->where('sales_records.merchant_id', $merchant->id)
            ->where('sales_records.sale_date', '>=', $start)
            ->selectRaw('sales_records.sale_date as sale_date, COALESCE(sale_items.unit_cost, 0) * sale_items.quantity as cogs')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->sale_date)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('cogs'));

        $invoiceCogs = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.merchant_id', $merchant->id)
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->whereNotNull('invoices.paid_at')
            ->where('invoices.paid_at', '>=', $start)
            ->selectRaw('invoices.paid_at as paid_at, COALESCE(invoice_items.unit_cost, 0) * invoice_items.quantity as cogs')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->paid_at)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('cogs'));

        $expenses = StoreAccess::limit($merchant->expenses())
            ->where('expense_date', '>=', $start)
            ->get(['expense_date', 'amount'])
            ->groupBy(fn ($row) => Carbon::parse($row->expense_date)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $salesByMonth = $periods->map(fn ($p) => ($sales[$p->format('Y-m')] ?? 0.0) + ($invoiceSales[$p->format('Y-m')] ?? 0.0))->all();
        $cogsByMonth = $periods->map(fn ($p) => ($posCogs[$p->format('Y-m')] ?? 0.0) + ($invoiceCogs[$p->format('Y-m')] ?? 0.0))->all();
        $expensesByMonth = $periods->map(fn ($p) => $expenses[$p->format('Y-m')] ?? 0.0)->all();

        return [
            'start' => $start->toDateString(),
            'labels' => $periods->map(fn ($p) => $p->format('M Y'))->all(),
            'sales' => $salesByMonth,
            'expenses' => $expensesByMonth,
            'profit' => array_map(fn ($s, $c, $e) => $s - $c - $e, $salesByMonth, $cogsByMonth, $expensesByMonth),
        ];
    }
}
