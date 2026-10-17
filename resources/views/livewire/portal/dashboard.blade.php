<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
            <div>
                <p class="text-[11px] uppercase tracking-wide text-ink-mute">Sales &middot; {{ $summary['period_label'] }}</p>
                <p class="text-[30px] font-light leading-none tracking-tight tnum text-ink mt-1">TZS {{ number_format($summary['sales_total'], 0) }}</p>
            </div>
            <div class="flex items-center gap-1 bg-canvas-soft rounded-pill p-1">
                @foreach(\App\Services\DashboardMetricsService::PERIOD_LABELS as $key => $label)
                    <button
                        wire:click="setPeriod('{{ $key }}')"
                        class="px-3.5 py-1.5 rounded-pill text-[13px] transition {{ $summary['period'] === $key ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}"
                    >{{ ucfirst($key) }}</button>
                @endforeach
            </div>
        </div>

        <x-ui.chart
            type="line"
            :labels="$summary['trend']['labels']"
            :height="220"
            :datasets="[
                [
                    'label' => 'Sales',
                    'data' => $summary['trend']['sales'],
                    'borderColor' => '#001830',
                    'backgroundColor' => 'rgba(0,24,48,0.06)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Expenses',
                    'data' => $summary['trend']['expenses'],
                    'borderColor' => '#ea2261',
                    'backgroundColor' => 'rgba(234,34,97,0.04)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Profit',
                    'data' => $summary['trend']['profit'],
                    'borderColor' => '#1b9e5a',
                    'backgroundColor' => 'rgba(27,158,90,0.04)',
                    'fill' => false,
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
            ]"
            :options="[
                'scales' => [
                    'y' => ['ticks' => ['display' => false], 'grid' => ['display' => false], 'border' => ['display' => false]],
                    'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['color' => '#64748d', 'font' => ['size' => 11]]],
                ],
            ]"
        />
        <div class="flex items-center gap-4 mt-3 text-[12px] text-ink-mute">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-brand-dark"></span> Sales</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-ruby"></span> Expenses</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:#1b9e5a"></span> Profit</span>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui.card class="lg:col-span-2">
            <h2 class="text-[15px] text-ink-secondary mb-4">Today</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-ui.stat label="Sales" value="TZS {{ number_format($summary['today_sales_total'], 0) }}" tone="primary" />
                <x-ui.stat label="Profit" value="TZS {{ number_format($summary['today_profit_total'], 0) }}" :tone="$summary['today_profit_total'] >= 0 ? 'primary' : 'ruby'" />
                <x-ui.stat label="Expenses" value="TZS {{ number_format($summary['today_expenses_total'], 0) }}" tone="ruby" />
                <x-ui.stat label="Unpaid today" value="TZS {{ number_format($summary['unpaid_today'], 0) }}" tone="lemon" />
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">{{ $summary['period_label'] }}</h2>
            <div class="grid grid-cols-2 gap-4">
                <x-ui.stat label="Profit" value="TZS {{ number_format($summary['profit_total'], 0) }}" :tone="$summary['profit_total'] >= 0 ? 'primary' : 'ruby'" />
                <x-ui.stat label="Total unpaid" value="TZS {{ number_format($summary['unpaid_total'], 0) }}" tone="lemon" />
            </div>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (cost)" value="TZS {{ number_format($summary['stock_summary']['buying_value'], 0) }}" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (selling)" value="TZS {{ number_format($summary['stock_summary']['selling_value'], 0) }}" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total stores" :value="$summary['total_stores']" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Payments awaiting verification" :value="$summary['unverified_payments_count']" tone="lemon" />
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Low stock items</h2>
            @forelse($lowStockItems as $item)
                <div class="flex items-center justify-between py-2 border-b border-hairline last:border-0 text-[13px]">
                    <span class="text-ink-secondary">{{ $item->name }}</span>
                    <span class="tnum text-ruby font-medium">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }} left</span>
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">Nothing low on stock right now.</p>
            @endforelse
            <a href="{{ route('portal.inventory.index') }}" class="inline-block mt-3 text-[13px] text-primary hover:text-primary-deep">Manage inventory &rarr;</a>
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Expiring soon</h2>
            @forelse($expiringSoonItems as $item)
                <div class="flex items-center justify-between py-2 border-b border-hairline last:border-0 text-[13px]">
                    <span class="text-ink-secondary">{{ $item->name }}</span>
                    <span class="tnum text-lemon font-medium">{{ $item->expiry_date->format('d M Y') }}</span>
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">Nothing expiring within a month.</p>
            @endforelse
            <a href="{{ route('portal.inventory.index') }}" class="inline-block mt-3 text-[13px] text-primary hover:text-primary-deep">Manage inventory &rarr;</a>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] text-ink-secondary">Recent payments</h2>
                <a href="{{ route('portal.payments.index') }}" class="text-[13px] text-primary hover:text-primary-deep">View all &rarr;</a>
            </div>
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left text-ink-mute border-b border-hairline">
                        <th class="pb-2 font-normal">Date</th>
                        <th class="pb-2 font-normal">Amount</th>
                        <th class="pb-2 font-normal">Payer</th>
                        <th class="pb-2 font-normal">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $payment)
                        <tr class="border-b border-hairline last:border-0">
                            <td class="py-2 text-ink-secondary">{{ $payment->payment_date->format('d M Y') }}</td>
                            <td class="py-2 tnum text-ink">TZS {{ number_format($payment->amount, 0) }}</td>
                            <td class="py-2 text-ink-secondary">{{ $payment->payer_name ?: '—' }}</td>
                            <td class="py-2">
                                <x-ui.badge :tone="$payment->status === 'verified' ? 'success' : ($payment->status === 'flagged' ? 'danger' : 'warning')">
                                    {{ $payment->status }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-ink-mute">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>

    <div class="flex gap-3 text-[13px] flex-wrap">
        <a href="{{ route('portal.sales.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Record a sale &rarr;</a>
        <a href="{{ route('portal.expenses.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Record an expense &rarr;</a>
        <a href="{{ route('portal.payments.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Record a payment &rarr;</a>
        <a href="{{ route('portal.inventory.index') }}" class="bg-canvas border border-hairline rounded-pill px-4 py-2 hover:border-primary/40 text-ink-secondary">Manage stock &rarr;</a>
    </div>
</div>
