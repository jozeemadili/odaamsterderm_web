<?php

namespace App\Livewire\Admin\Reports;

use App\Models\Order;
use App\Services\MarketplaceMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Marketplace activity: orders, GMV, fulfilment, top products and sellers. */
#[Layout('layouts.admin', ['title' => 'Marketplace Report'])]
class Marketplace extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('monitor-marketplace'), 403);
        $this->from = $this->from ?: now()->subDays(29)->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function preset(int $days): void
    {
        $this->from = now()->subDays($days - 1)->toDateString();
        $this->to = now()->toDateString();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function period(): array
    {
        $from = rescue(fn () => Carbon::parse($this->from), now()->subDays(29), false);
        $to = rescue(fn () => Carbon::parse($this->to), now(), false);

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    public function exportCsv()
    {
        [$from, $to] = $this->period();

        $orders = Order::with(['merchant', 'branch'])
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->oldest()->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Placed', 'Status', 'Seller', 'Store', 'Customer', 'Phone', 'Items', 'Total (TZS)', 'Completed', 'Reason']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->reference, $o->created_at->toDateTimeString(), $o->statusLabel(), $o->merchant->business_name,
                    $o->branch->name, $o->customer_name, $o->customer_phone, $o->items_count, (float) $o->total,
                    $o->completed_at?->toDateTimeString(), $o->status_reason,
                ]);
            }
            fclose($out);
        }, "marketplace-orders-{$from->toDateString()}-to-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }

    public function render(MarketplaceMonitor $monitor)
    {
        [$from, $to] = $this->period();

        return view('livewire.admin.reports.marketplace', ['report' => $monitor->report($from, $to)]);
    }
}
