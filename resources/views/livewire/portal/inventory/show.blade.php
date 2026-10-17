<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-start gap-4">
            @if($item->image())
                <img src="{{ $item->image()->getUrl() }}" alt="" class="h-20 w-20 rounded-lg object-cover border border-hairline">
            @else
                <div class="h-20 w-20 rounded-lg bg-canvas-soft border border-hairline flex items-center justify-center text-ink-mute text-[11px]">No image</div>
            @endif
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-[20px] font-light text-ink tracking-tight">{{ $item->name }}</h2>
                    @if($item->isLowStock())
                        <x-ui.badge tone="danger">low stock</x-ui.badge>
                    @endif
                    @if($item->isExpiringSoon())
                        <x-ui.badge tone="warning">expires {{ $item->expiry_date->format('d M Y') }}</x-ui.badge>
                    @elseif($item->isExpired())
                        <x-ui.badge tone="danger">expired {{ $item->expiry_date->format('d M Y') }}</x-ui.badge>
                    @endif
                    @if($item->branch)
                        <x-ui.badge tone="primary">{{ $item->branch->name }}</x-ui.badge>
                    @endif
                </div>
                <p class="text-[13px] text-ink-mute mt-1">
                    {{ $item->category?->name ?? 'Uncategorized' }}
                    @if($item->sku) &middot; SKU {{ $item->sku }} @endif
                    @if($item->barcode) &middot; Barcode {{ $item->barcode }} @endif
                    @if($item->expiry_date && ! $item->isExpiringSoon() && ! $item->isExpired()) &middot; Expires {{ $item->expiry_date->format('d M Y') }} @endif
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-[13px]">
                    <div><p class="text-ink-mute mb-1">On hand</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}</p></div>
                    <div><p class="text-ink-mute mb-1">Reorder level</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->reorder_level, '0'), '.') }}</p></div>
                    <div><p class="text-ink-mute mb-1">Unit cost</p><p class="text-ink font-medium tnum">{{ $item->unit_cost ? number_format($item->unit_cost, 0) : '—' }}</p></div>
                    <div><p class="text-ink-mute mb-1">Selling price</p><p class="text-ink font-medium tnum">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</p></div>
                </div>
            </div>
            <a href="{{ route('portal.inventory.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Back</a>
        </div>

        @if($item->barcode && $this->barcodeSvg())
            <div class="mt-4 pt-4 border-t border-hairline flex items-center gap-4">
                <div class="bg-white p-2 rounded border border-hairline [&_svg]:h-16">{!! $this->barcodeSvg() !!}</div>
                <p class="text-[12px] text-ink-mute">Print and attach this barcode to the physical product for scanning at checkout.</p>
            </div>
        @endif
    </x-ui.card>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Units sold (all time)" :value="rtrim(rtrim(number_format($unitsSold, 2, '.', ''), '0'), '.')" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Revenue" value="TZS {{ number_format($revenue, 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Discounts given" value="TZS {{ number_format($discountGiven, 0) }}" tone="ruby" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Est. profit" value="TZS {{ number_format($estimatedProfit, 0) }}" :tone="$estimatedProfit >= 0 ? 'primary' : 'ruby'" />
            <p class="text-[11px] text-ink-mute mt-1">{{ $marginPercent }}% margin &middot; based on current unit cost</p>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-2 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Units received (approved receipts)" :value="rtrim(rtrim(number_format($unitsReceived, 2, '.', ''), '0'), '.')" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total spent purchasing" value="TZS {{ number_format($totalPurchaseCost, 0) }}" />
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Purchase history</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Supplier</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit cost</th>
                    <th class="px-5 py-3 font-normal text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($purchases as $p)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $p->stockReceipt->receipt_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $p->stockReceipt->supplier?->name ?? '—' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($p->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ number_format($p->unit_cost, 0) }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($p->subtotal, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-6 text-center text-ink-mute">No approved purchases yet.</td></tr>
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
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit price</th>
                    <th class="px-5 py-3 font-normal">Discount</th>
                    <th class="px-5 py-3 font-normal text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($sales as $s)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $s->sale->sale_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $s->sale->customer?->name ?? 'Walk-in' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($s->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ number_format($s->unit_price, 0) }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-mute">{{ $s->discount_amount > 0 ? '−'.number_format($s->discount_amount, 0) : '—' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($s->subtotal, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-ink-mute">Not sold yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Stock movement log</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Type</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Reference</th>
                    <th class="px-5 py-3 font-normal">By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($movements as $m)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $m->movement_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5">
                            <x-ui.badge :tone="$m->type === 'in' ? 'success' : ($m->type === 'out' ? 'danger' : 'neutral')">{{ $m->type }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($m->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 text-ink-mute">{{ $m->notes ?: ucfirst(str_replace('_', ' ', (string) $m->reference)) }}</td>
                        <td class="px-5 py-2.5 text-ink-mute">{{ $m->recordedBy?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-6 text-center text-ink-mute">No stock movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
