<div class="space-y-4">
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-4">
        <x-ui.card padding="p-5"><x-ui.stat label="Approved sellers" :value="$overview['approved_sellers']" sub="{{ $overview['pending_sellers'] }} pending" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Live stores" :value="$overview['live_stores']" tone="primary" sub="{{ $overview['suspended_stores'] }} suspended" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Listed offers" :value="$overview['listed_offers']" sub="{{ $overview['listed_products'] }} products" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Units online" :value="number_format($overview['units_online'])" /></x-ui.card>
        <x-ui.card padding="p-5"><a href="{{ route('admin.orders.index') }}"><x-ui.stat label="Open orders" :value="$overview['open_orders']" sub="{{ $overview['pending_orders'] }} new" /></a></x-ui.card>
        <x-ui.card padding="p-5"><a href="{{ route('admin.marketplace.exceptions') }}"><x-ui.stat label="Exceptions" :value="$overview['exceptions']" :tone="$overview['exceptions'] ? 'ruby' : 'ink'" /></a></x-ui.card>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @foreach(['live' => 'Live listings', 'out_of_stock' => 'Out of stock', 'hidden' => 'Hidden', 'all' => 'All linked items'] as $key => $label)
            <button wire:click="$set('show', '{{ $key }}')" class="text-[12px] px-3 py-1 rounded-pill border {{ $show === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $label }}</button>
        @endforeach
        <div class="ml-auto flex gap-3">
            <div class="w-56"><x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search product…" id="mk_admin_search" /></div>
            <div class="w-56">
                <x-ui.select wire:model.live="seller" id="mk_admin_seller">
                    <option value="">All sellers</option>
                    @foreach($sellers as $s)<option value="{{ $s->id }}">{{ $s->business_name }}</option>@endforeach
                </x-ui.select>
            </div>
        </div>
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[900px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Seller · store</th>
                    <th class="px-5 py-3 font-normal">Price</th>
                    <th class="px-5 py-3 font-normal">Actual stock</th>
                    <th class="px-5 py-3 font-normal">Reserved</th>
                    <th class="px-5 py-3 font-normal">Online qty</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($listings as $item)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3"><p class="text-ink font-medium">{{ $item->product?->displayName() ?? $item->name }}</p><p class="text-[11px] text-ink-mute">{{ $item->product?->animalType?->name }}</p></td>
                        <td class="px-5 py-3 text-ink-secondary"><a href="{{ route('admin.merchants.show', $item->merchant) }}" class="text-primary">{{ $item->merchant->business_name }}</a><br><span class="text-[11px] text-ink-mute">{{ $item->branch?->name }}</span></td>
                        <td class="px-5 py-3 tnum">{{ $item->unit_price !== null ? number_format($item->unit_price) : '—' }}</td>
                        <td class="px-5 py-3 tnum">{{ rtrim(rtrim((string) $item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}</td>
                        <td class="px-5 py-3 tnum">{{ rtrim(rtrim((string) $item->reserved_quantity, '0'), '.') }}</td>
                        <td class="px-5 py-3 tnum font-medium">{{ rtrim(rtrim((string) $item->marketplace_quantity, '0'), '.') }}</td>
                        <td class="px-5 py-3">
                            @if(! $item->is_marketplace_visible)
                                <x-ui.badge tone="neutral">hidden</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$item->marketplace_status === 'active' ? 'success' : ($item->marketplace_status === 'out_of_stock' ? 'danger' : 'neutral')">{{ str_replace('_', ' ', $item->marketplace_status) }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No listings match.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $listings->links() }}
</div>
