<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-[20px] font-light text-ink tracking-tight">{{ $customer->name }}</h2>
                <p class="text-[13px] text-ink-mute mt-1">
                    {{ $customer->customer_code }}
                    @if($customer->phone) &middot; {{ $customer->phone }} @endif
                    @if($customer->email) &middot; {{ $customer->email }} @endif
                </p>
                @if($customer->credit_allowed)
                    <x-ui.badge tone="primary" class="mt-2">Credit up to TZS {{ number_format($customer->credit_limit, 0) }}</x-ui.badge>
                @endif
            </div>
            <a href="{{ route('portal.customers.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Back</a>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total amount paid" value="TZS {{ number_format($totalPaid, 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total discount received" value="TZS {{ number_format($totalDiscount, 0) }}" tone="ruby" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Est. profit generated" value="TZS {{ number_format($estimatedProfit, 0) }}" :tone="$estimatedProfit >= 0 ? 'primary' : 'ruby'" />
            <p class="text-[11px] text-ink-mute mt-1">based on current unit cost</p>
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Items purchased</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Qty purchased</th>
                    <th class="px-5 py-3 font-normal text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($itemsPurchased as $line)
                    <tr>
                        <td class="px-5 py-2.5 text-ink font-medium">{{ $line['name'] }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim(number_format($line['quantity'], 2, '.', ''), '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($line['amount'], 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-6 text-center text-ink-mute">No purchases yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Invoices</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Number</th>
                    <th class="px-5 py-3 font-normal">Issue date</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal text-right">Total</th>
                    <th class="px-5 py-3 font-normal text-right">Balance due</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="px-5 py-2.5 text-ink font-medium">{{ $invoice->number }}</td>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5">
                            <x-ui.badge :tone="match($invoice->status) {
                                'paid' => 'success',
                                'partially_paid' => 'warning',
                                'cancelled' => 'danger',
                                'invoiced' => 'primary',
                                default => 'neutral',
                            }">{{ $invoice->statusLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($invoice->total, 0) }}</td>
                        <td class="px-5 py-2.5 tnum text-right {{ $invoice->balanceDue() > 0 ? 'text-ruby' : 'text-ink-mute' }}">{{ number_format($invoice->balanceDue(), 0) }}</td>
                        <td class="px-5 py-2.5 text-right">
                            <a href="{{ route('portal.invoices.show', $invoice) }}" class="text-primary hover:text-primary-deep">View &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-ink-mute">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Sales history</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Items</th>
                    <th class="px-5 py-3 font-normal">Discount</th>
                    <th class="px-5 py-3 font-normal">Served by</th>
                    <th class="px-5 py-3 font-normal text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($sales as $sale)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $sale->sale_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ $sale->items_count ?? $sale->items->count() }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-mute">{{ $sale->discount_amount > 0 ? '−'.number_format($sale->discount_amount, 0) : '—' }}</td>
                        <td class="px-5 py-2.5 text-ink-mute">{{ $sale->recordedBy?->name }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($sale->amount, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-6 text-center text-ink-mute">No sales yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
