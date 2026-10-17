<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform-level view of the marketplace for administrators: health
 * numbers, inventory/marketplace exceptions that need attention, and
 * activity reports.
 */
class MarketplaceMonitor
{
    /** Orders waiting this long for the seller are flagged. */
    public const STALE_PENDING_HOURS = 24;

    /** Orders left uncollected this long after "ready" are flagged. */
    public const STALE_READY_HOURS = 72;

    private const EPSILON = 0.001;

    /** @return array<string, int|float> */
    public function overview(): array
    {
        $listed = InventoryItem::query()->onMarketplace();

        return [
            'approved_sellers' => Merchant::marketplaceApproved()->count(),
            'pending_sellers' => Merchant::whereIn('kyc_status', [Merchant::KYC_PENDING, Merchant::KYC_UNDER_REVIEW])->count(),
            'live_stores' => Branch::marketplaceReady()->whereHas('merchant', fn (Builder $m) => $m->marketplaceApproved())->count(),
            'suspended_stores' => Branch::where('status', Branch::STATUS_SUSPENDED)->count(),
            'stores_without_location' => Branch::where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count(),
            'listed_offers' => (clone $listed)->count(),
            'listed_products' => (clone $listed)->distinct()->count('product_id'),
            'units_online' => (float) (clone $listed)->sum('marketplace_quantity'),
            'pending_products' => Product::where('status', Product::STATUS_PENDING)->count(),
            'open_orders' => Order::open()->count(),
            'pending_orders' => Order::where('status', Order::PENDING)->count(),
            'ready_orders' => Order::where('status', Order::READY)->count(),
            'gmv_today' => (float) Order::where('status', Order::COMPLETED)->whereDate('completed_at', today())->sum('total'),
            'gmv_30_days' => (float) Order::where('status', Order::COMPLETED)->where('completed_at', '>=', now()->subDays(30))->sum('total'),
            'exceptions' => collect($this->exceptions())->where('severity', '!=', 'info')->sum('count'),
        ];
    }

    /**
     * Each check: key, severity (critical|warning|info), title, what it
     * means, how many, and the affected rows (inventory items or orders).
     *
     * @return array<int, array{key:string, severity:string, title:string, description:string, kind:string, count:int, rows:Collection}>
     */
    public function exceptions(): array
    {
        $items = fn () => InventoryItem::query()->with(['merchant', 'branch', 'product']);
        $orders = fn () => Order::query()->with(['merchant', 'branch']);

        $checks = [
            [
                'key' => 'reserved_exceeds_stock',
                'severity' => 'critical',
                'title' => 'Reserved units exceed physical stock',
                'description' => 'More units are held for open orders than the store has. Orders may not be fulfillable — check the stock count with the seller.',
                'kind' => 'item',
                'rows' => $items()->whereColumn('reserved_quantity', '>', 'quantity_on_hand')->get(),
            ],
            [
                'key' => 'marketplace_exceeds_available',
                'severity' => 'critical',
                'title' => 'Marketplace quantity above unreserved stock',
                'description' => 'Customers can order more than the store can supply (overselling risk).',
                'kind' => 'item',
                'rows' => $items()
                    ->where('is_marketplace_visible', true)
                    ->whereRaw('marketplace_quantity > quantity_on_hand - reserved_quantity + ?', [self::EPSILON])
                    ->get(),
            ],
            [
                'key' => 'reservation_drift',
                'severity' => 'critical',
                'title' => 'Reservations out of sync with open orders',
                'description' => 'The reserved quantity differs from the units in this item\'s open orders.',
                'kind' => 'item',
                'rows' => $items()
                    ->select('inventory_items.*')
                    ->selectSub($this->openOrderUnitsSql(), 'open_order_units')
                    ->whereRaw('ABS(reserved_quantity - ('.$this->openOrderUnitsSql()->toSql().')) > (? + 0)', [...$this->openOrderUnitsSql()->getBindings(), self::EPSILON])
                    ->get(),
            ],
            [
                'key' => 'negative_stock',
                'severity' => 'critical',
                'title' => 'Negative stock',
                'description' => 'Physical stock below zero — usually stock sold before it was received.',
                'kind' => 'item',
                'rows' => $items()->where('quantity_on_hand', '<', 0)->get(),
            ],
            [
                'key' => 'stale_pending_orders',
                'severity' => 'warning',
                'title' => 'Orders waiting over '.self::STALE_PENDING_HOURS.' hours for the seller',
                'description' => 'The seller has not accepted or rejected these orders. Units stay reserved meanwhile.',
                'kind' => 'order',
                'rows' => $orders()->where('status', Order::PENDING)->where('created_at', '<', now()->subHours(self::STALE_PENDING_HOURS))->oldest()->get(),
            ],
            [
                'key' => 'stale_ready_orders',
                'severity' => 'warning',
                'title' => 'Ready orders not collected after '.self::STALE_READY_HOURS.' hours',
                'description' => 'The customer has not collected these. The seller can reject them to release the stock.',
                'kind' => 'order',
                'rows' => $orders()->where('status', Order::READY)->where('ready_at', '<', now()->subHours(self::STALE_READY_HOURS))->oldest('ready_at')->get(),
            ],
            [
                'key' => 'hidden_listings',
                'severity' => 'warning',
                'title' => 'Listings switched on but invisible to customers',
                'description' => 'The seller made these visible, but an unapproved product, a store without location or suspended, or an unapproved seller keeps them off the marketplace.',
                'kind' => 'item',
                'rows' => $items()
                    ->where('is_marketplace_visible', true)
                    ->where('marketplace_status', InventoryItem::MARKET_ACTIVE)
                    ->whereNotIn('id', InventoryItem::query()->onMarketplace()->select('inventory_items.id'))
                    ->get(),
            ],
            [
                'key' => 'missing_price',
                'severity' => 'warning',
                'title' => 'Visible listings without a selling price',
                'description' => 'Customers see no price and orders would total zero.',
                'kind' => 'item',
                'rows' => $items()->where('is_marketplace_visible', true)->where(fn (Builder $q) => $q->whereNull('unit_price')->orWhere('unit_price', '<=', 0))->get(),
            ],
            [
                'key' => 'out_of_stock_listings',
                'severity' => 'info',
                'title' => 'Listings out of stock online',
                'description' => 'Shown to customers as "Out of stock". Sellers can expose more stock to relist.',
                'kind' => 'item',
                'rows' => $items()->where('is_marketplace_visible', true)->where('marketplace_status', InventoryItem::MARKET_OUT_OF_STOCK)->get(),
            ],
        ];

        return array_map(fn ($c) => $c + ['count' => $c['rows']->count()], $checks);
    }

    /** Units of an inventory item held by open orders (correlated subquery). */
    protected function openOrderUnitsSql(): \Illuminate\Database\Query\Builder
    {
        return OrderItem::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereColumn('order_items.inventory_item_id', 'inventory_items.id')
            ->whereIn('orders.status', Order::OPEN);
    }

    /**
     * Marketplace activity for a period.
     *
     * @return array<string, mixed>
     */
    public function report(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $placed = Order::whereBetween('created_at', [$from, $to]);
        $completed = Order::where('status', Order::COMPLETED)->whereBetween('completed_at', [$from, $to]);
        $closedInPeriod = Order::whereIn('status', [Order::COMPLETED, Order::REJECTED, Order::CANCELLED])
            ->where(fn (Builder $q) => $q->whereBetween('completed_at', [$from, $to])->orWhereBetween('closed_at', [$from, $to]))
            ->selectRaw('status, COUNT(*) as aggregate_count')->groupBy('status')->pluck('aggregate_count', 'status');

        $finished = (int) $closedInPeriod->sum();
        $readyTimes = Order::whereBetween('ready_at', [$from, $to])->get(['created_at', 'ready_at'])
            ->map(fn (Order $o) => $o->created_at->diffInMinutes($o->ready_at));

        $completedIds = (clone $completed)->pluck('id');

        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->toDateString());
        }
        $placedByDay = (clone $placed)->get(['created_at'])->groupBy(fn ($o) => $o->created_at->toDateString())->map->count();
        $gmvByDay = (clone $completed)->get(['completed_at', 'total'])->groupBy(fn ($o) => $o->completed_at->toDateString())->map(fn ($g) => (float) $g->sum('total'));

        $sales = SalesRecord::whereDate('sale_date', '>=', $from->toDateString())
            ->whereDate('sale_date', '<=', $to->toDateString())
            ->selectRaw('source, SUM(amount) as total, COUNT(*) as count')->groupBy('source')->get()->keyBy('source');

        return [
            'from' => $from,
            'to' => $to,
            'orders_placed' => (clone $placed)->count(),
            'orders_by_status' => (clone $placed)->selectRaw('status, COUNT(*) as aggregate_count')->groupBy('status')->pluck('aggregate_count', 'status')->all(),
            'orders_completed' => $completedIds->count(),
            'gmv' => (float) (clone $completed)->sum('total'),
            'average_order_value' => $completedIds->count() ? (float) (clone $completed)->avg('total') : 0.0,
            'fulfilment_rate' => $finished ? round(($closedInPeriod[Order::COMPLETED] ?? 0) / $finished * 100, 1) : null,
            'rejected' => (int) ($closedInPeriod[Order::REJECTED] ?? 0),
            'cancelled' => (int) ($closedInPeriod[Order::CANCELLED] ?? 0),
            'avg_minutes_to_ready' => $readyTimes->isNotEmpty() ? (int) round($readyTimes->avg()) : null,
            'online_sales' => (float) ($sales[SalesRecord::SOURCE_ONLINE]->total ?? 0),
            'offline_sales' => (float) $sales->except([SalesRecord::SOURCE_ONLINE])->sum('total'),
            'top_products' => OrderItem::whereIn('order_id', $completedIds)
                ->selectRaw('name, SUM(quantity) as units, SUM(line_total) as revenue')
                ->groupBy('name')->orderByDesc('revenue')->limit(10)->get(),
            'top_sellers' => Order::whereIn('orders.id', $completedIds)
                ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
                ->selectRaw('merchants.business_name as seller, COUNT(*) as orders, SUM(orders.total) as revenue')
                ->groupBy('merchants.business_name')->orderByDesc('revenue')->limit(10)->get(),
            'rejection_reasons' => Order::where('status', Order::REJECTED)->whereBetween('closed_at', [$from, $to])
                ->whereNotNull('status_reason')->selectRaw('status_reason, COUNT(*) as aggregate_count')
                ->groupBy('status_reason')->orderByDesc('aggregate_count')->limit(5)->pluck('aggregate_count', 'status_reason')->all(),
            'daily' => [
                'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('d M'))->all(),
                'orders' => $days->map(fn ($d) => (int) ($placedByDay[$d] ?? 0))->all(),
                'gmv' => $days->map(fn ($d) => (float) ($gmvByDay[$d] ?? 0))->all(),
            ],
        ];
    }
}
