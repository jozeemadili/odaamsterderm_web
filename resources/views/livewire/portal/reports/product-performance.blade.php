<div class="space-y-4">
    <div class="flex items-center gap-1 bg-canvas-soft rounded-pill p-1 w-fit">
        <button wire:click="setTab('most_sold')" class="px-4 py-1.5 rounded-pill text-[13px] transition {{ $tab === 'most_sold' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Most sold</button>
        <button wire:click="setTab('most_profitable')" class="px-4 py-1.5 rounded-pill text-[13px] transition {{ $tab === 'most_profitable' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Most profitable</button>
    </div>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal w-10">#</th>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Store</th>
                    <th class="px-5 py-3 font-normal">Qty sold</th>
                    <th class="px-5 py-3 font-normal">Sales</th>
                    <th class="px-5 py-3 font-normal">Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse(($tab === 'most_sold' ? $mostSold : $mostProfitable) as $i => $row)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink-mute tnum">{{ $i + 1 }}</td>
                        <td class="px-5 py-3 text-ink font-medium">{{ $row->product_name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $row->branch_name ?? '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ rtrim(rtrim(number_format($row->quantity_sold, 2, '.', ''), '0'), '.') }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">TZS {{ number_format($row->total_sales_amount, 0) }}</td>
                        <td class="px-5 py-3 tnum {{ $row->cost_lines_count > 0 ? 'text-ink font-medium' : 'text-ink-mute' }}">
                            {{ $row->cost_lines_count > 0 ? 'TZS '.number_format($row->total_profit_raw, 0) : 'N/A' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">No sales recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    @if($tab === 'most_profitable')
        <p class="text-[12px] text-ink-mute">Products with no cost-tracked sales are left out of this ranking rather than shown with a misleading profit.</p>
    @endif
</div>
