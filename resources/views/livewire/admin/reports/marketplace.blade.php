<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $days => $label)
            <button wire:click="preset({{ $days }})" class="text-[12px] px-3 py-1.5 rounded-pill border border-hairline text-ink-secondary hover:border-primary/40">{{ $label }}</button>
        @endforeach
        <div class="w-40"><x-ui.input type="date" wire:model.live="from" label="From" id="report_from" /></div>
        <div class="w-40"><x-ui.input type="date" wire:model.live="to" label="To" id="report_to" /></div>
        <x-ui.button variant="secondary" size="sm" wire:click="exportCsv" target="exportCsv" class="ml-auto">Download orders CSV</x-ui.button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <x-ui.card padding="p-5"><x-ui.stat label="Orders placed" :value="$report['orders_placed']" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Completed" :value="$report['orders_completed']" tone="primary" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Marketplace GMV" value="TZS {{ number_format($report['gmv']) }}" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Avg order value" value="TZS {{ number_format($report['average_order_value']) }}" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Fulfilment rate" :value="$report['fulfilment_rate'] !== null ? $report['fulfilment_rate'].'%' : '—'" sub="{{ $report['rejected'] }} rejected · {{ $report['cancelled'] }} cancelled" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Avg time to ready" :value="$report['avg_minutes_to_ready'] !== null ? ($report['avg_minutes_to_ready'] >= 120 ? round($report['avg_minutes_to_ready'] / 60, 1).' h' : $report['avg_minutes_to_ready'].' min') : '—'" /></x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui.card class="lg:col-span-2">
            <h2 class="text-[15px] text-ink-secondary mb-3">Orders placed and GMV per day</h2>
            <x-ui.chart
                type="bar"
                :labels="$report['daily']['labels']"
                :height="220"
                :datasets="[
                    ['label' => 'Orders', 'data' => $report['daily']['orders'], 'backgroundColor' => '#c89a44', 'yAxisID' => 'y', 'borderRadius' => 4],
                    ['label' => 'GMV (TZS)', 'data' => $report['daily']['gmv'], 'type' => 'line', 'borderColor' => '#01162f', 'backgroundColor' => 'rgba(1,22,47,0.06)', 'yAxisID' => 'y1', 'tension' => 0.35, 'pointRadius' => 0, 'borderWidth' => 2],
                ]"
                :options="['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['display' => false]]]]"
            />
        </x-ui.card>
        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-3">Sales by channel (all sellers)</h2>
            @php
                $totalSales = $report['online_sales'] + $report['offline_sales'];
            @endphp
            <div class="space-y-3 text-[13px]">
                <div><div class="flex justify-between"><span>Marketplace (online)</span><span class="tnum">TZS {{ number_format($report['online_sales']) }}</span></div>
                    <div class="h-2 bg-canvas-soft rounded-full mt-1"><div class="h-2 bg-primary rounded-full" style="width: {{ $totalSales > 0 ? round($report['online_sales'] / $totalSales * 100) : 0 }}%"></div></div></div>
                <div><div class="flex justify-between"><span>In store (POS, invoices)</span><span class="tnum">TZS {{ number_format($report['offline_sales']) }}</span></div>
                    <div class="h-2 bg-canvas-soft rounded-full mt-1"><div class="h-2 bg-brand-dark rounded-full" style="width: {{ $totalSales > 0 ? round($report['offline_sales'] / $totalSales * 100) : 0 }}%"></div></div></div>
            </div>
            <h3 class="text-[13px] text-ink-secondary mt-6 mb-2">Orders by status</h3>
            @forelse($report['orders_by_status'] as $status => $count)
                <div class="flex justify-between text-[13px] py-0.5"><span>{{ \App\Models\Order::LABELS[$status] ?? $status }}</span><span class="tnum">{{ $count }}</span></div>
            @empty
                <p class="text-[13px] text-ink-mute">No orders in this period.</p>
            @endforelse
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui.card padding="p-0">
            <div class="px-5 py-3 border-b border-hairline"><h2 class="text-[15px] text-ink-secondary">Top products (completed orders)</h2></div>
            <table class="w-full text-[13px]"><tbody class="divide-y divide-hairline">
                @forelse($report['top_products'] as $row)
                    <tr><td class="px-5 py-2.5">{{ $row->name }}</td><td class="px-5 py-2.5 tnum text-ink-mute">{{ rtrim(rtrim((string) $row->units, '0'), '.') }} units</td><td class="px-5 py-2.5 tnum text-right">TZS {{ number_format($row->revenue) }}</td></tr>
                @empty
                    <tr><td class="px-5 py-6 text-center text-ink-mute">No completed orders yet.</td></tr>
                @endforelse
            </tbody></table>
        </x-ui.card>
        <x-ui.card padding="p-0">
            <div class="px-5 py-3 border-b border-hairline"><h2 class="text-[15px] text-ink-secondary">Top sellers</h2></div>
            <table class="w-full text-[13px]"><tbody class="divide-y divide-hairline">
                @forelse($report['top_sellers'] as $row)
                    <tr><td class="px-5 py-2.5">{{ $row->seller }}</td><td class="px-5 py-2.5 tnum text-ink-mute">{{ $row->orders }} orders</td><td class="px-5 py-2.5 tnum text-right">TZS {{ number_format($row->revenue) }}</td></tr>
                @empty
                    <tr><td class="px-5 py-6 text-center text-ink-mute">No completed orders yet.</td></tr>
                @endforelse
            </tbody></table>
        </x-ui.card>
        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-3">Why orders were rejected</h2>
            @forelse($report['rejection_reasons'] as $reason => $count)
                <div class="flex justify-between gap-3 text-[13px] py-1"><span>{{ $reason }}</span><span class="tnum">{{ $count }}</span></div>
            @empty
                <p class="text-[13px] text-ink-mute">No rejections in this period.</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
