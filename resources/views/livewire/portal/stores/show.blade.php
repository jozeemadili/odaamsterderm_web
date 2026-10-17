<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-[20px] font-light text-ink tracking-tight">{{ $store->name }}</h2>
                    @if($store->is_primary)
                        <x-ui.badge tone="primary">primary</x-ui.badge>
                    @endif
                </div>
                <p class="text-[13px] text-ink-mute mt-1">{{ $store->address }} {{ $store->phone }}</p>
            </div>
            <a href="{{ route('portal.stores.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Back</a>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (at cost)" value="TZS {{ number_format($summary['stockValue'], 0) }}" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (at selling price)" value="TZS {{ number_format($summary['sellingValue'], 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Expected profit if sold out" value="TZS {{ number_format($summary['expectedProfit'], 0) }}" :tone="$summary['expectedProfit'] >= 0 ? 'primary' : 'ruby'" />
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Products in this store</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Item</th>
                    <th class="px-5 py-3 font-normal">On hand</th>
                    <th class="px-5 py-3 font-normal">Unit cost</th>
                    <th class="px-5 py-3 font-normal">Selling price</th>
                    <th class="px-5 py-3 font-normal text-right">Stock value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($items as $item)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium"><a href="{{ route('portal.inventory.show', $item) }}" class="hover:text-primary-deep">{{ $item->name }}</a></td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->unit_cost ? number_format($item->unit_cost, 0) : '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink text-right">{{ number_format($item->quantity_on_hand * ($item->unit_cost ?? 0), 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No products assigned to this store yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
