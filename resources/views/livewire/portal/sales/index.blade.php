<div class="space-y-4">
    <x-ui.card padding="p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[12px] text-ink-mute mb-1">From</label>
                <input type="date" wire:model.live="dateFrom" class="rounded-sm border border-hairline-input bg-canvas text-[13px] px-2.5 py-1.5">
            </div>
            <div>
                <label class="block text-[12px] text-ink-mute mb-1">To</label>
                <input type="date" wire:model.live="dateTo" class="rounded-sm border border-hairline-input bg-canvas text-[13px] px-2.5 py-1.5">
            </div>
            <div class="flex-1"></div>
            <x-ui.button variant="secondary" size="sm" wire:click="exportExcel" target="exportExcel">Download Excel</x-ui.button>
            <x-ui.button variant="secondary" size="sm" wire:click="exportPdf" target="exportPdf">Download PDF</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-mute mb-3">Totals for this period</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-ui.stat label="Buying value" value="TZS {{ number_format($costSummary['buying_value'], 0) }}" />
            <x-ui.stat label="Selling value" value="TZS {{ number_format($costSummary['selling_value'], 0) }}" tone="primary" />
            <x-ui.stat label="Profit" value="TZS {{ number_format($costSummary['profit'], 0) }}" :tone="$costSummary['profit'] >= 0 ? 'primary' : 'ruby'" />
        </div>
        @if($costSummary['partial'])
            <p class="text-[12px] text-ink-mute mt-3">Buying value and profit only include sales with a recorded cost price — sales made before cost tracking started aren't guessed at.</p>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Time</th>
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Amount</th>
                    <th class="px-5 py-3 font-normal">Items</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($sales as $sale)
                    <tr class="hover:bg-canvas-soft/60 {{ $sale->items_count > 0 ? 'cursor-pointer' : '' }}" @if($sale->items_count > 0) wire:click="toggleExpand({{ $sale->id }})" @endif>
                        <td class="px-5 py-3 text-ink-secondary">{{ $sale->sale_date->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-ink-mute tnum">{{ $sale->created_at->format('H:i') }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                        <td class="px-5 py-3 tnum text-ink font-medium">TZS {{ number_format($sale->amount, 0) }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">
                            {{ $sale->items_count ?? '—' }}
                            @if($sale->items_count > 0)
                                <x-ui.badge tone="primary" class="ml-1">{{ $expandedSaleId === $sale->id ? 'hide' : 'view' }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                    @if($expandedSaleId === $sale->id)
                        <tr class="bg-canvas-soft/60">
                            <td colspan="5" class="px-5 py-3">
                                <table class="w-full text-[12px]">
                                    <thead class="text-ink-mute text-left">
                                        <tr>
                                            <th class="font-normal pb-1">Product</th>
                                            <th class="font-normal pb-1">Qty</th>
                                            <th class="font-normal pb-1">Unit price</th>
                                            <th class="font-normal pb-1">Discount</th>
                                            <th class="font-normal pb-1 text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-hairline/60">
                                        @foreach($sale->items as $item)
                                            <tr>
                                                <td class="py-1.5 text-ink-secondary">{{ $item->item_name }}</td>
                                                <td class="py-1.5 tnum text-ink-secondary">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                                                <td class="py-1.5 tnum text-ink-secondary">{{ number_format($item->unit_price, 0) }}</td>
                                                <td class="py-1.5 tnum text-ink-mute">{{ $item->discount_amount > 0 ? '−'.number_format($item->discount_amount, 0) : '—' }}</td>
                                                <td class="py-1.5 tnum text-ink text-right">{{ number_format($item->subtotal, 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if($sale->discount_amount > 0)
                                    <p class="text-[11px] text-ink-mute mt-2">Overall sale discount: TZS {{ number_format($sale->discount_amount, 0) }}
                                        @if($sale->discountApprovedBy)
                                            &middot; approved by {{ $sale->discountApprovedBy->name }}
                                        @endif
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $sales->links() }}</div>
</div>
