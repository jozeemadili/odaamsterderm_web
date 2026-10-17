<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @unless($merchant->canSellOnMarketplace())
        <div class="rounded-lg bg-canvas-cream border border-lemon/20 text-lemon px-4 py-3 text-[13px]">
            <strong>Your business is not approved for the marketplace yet.</strong>
            You can prepare listings now; customers will see them once the platform approves your business.
            <a href="{{ route('portal.kyc.index') }}" class="underline">Check your verification documents</a>.
        </div>
    @endunless

    @if($storesWithoutLocation->isNotEmpty())
        <div class="rounded-lg bg-canvas-cream border border-lemon/20 text-lemon px-4 py-3 text-[13px]">
            Customers find stores by distance, so items in a store without a map location are not listed:
            @foreach($storesWithoutLocation as $store)
                <a href="{{ route('portal.stores.show', $store) }}" class="underline">{{ $store->name }}</a>@if(! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-ui.card padding="p-5"><x-ui.stat label="Listed products" :value="$stats['listed']" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Units exposed online" :value="rtrim(rtrim(number_format($stats['units'], 2, '.', ','), '0'), '.')" tone="primary" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Out of stock online" :value="$stats['out_of_stock']" :tone="$stats['out_of_stock'] ? 'ruby' : null" /></x-ui.card>
        <x-ui.card padding="p-5"><x-ui.stat label="Not linked to catalog" :value="$stats['unlinked']" /></x-ui.card>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @foreach(['all' => 'All items', 'listed' => 'Listed', 'hidden' => 'Hidden', 'out_of_stock' => 'Out of stock'] as $key => $label)
            <button wire:click="$set('show', '{{ $key }}')" class="text-[12px] px-3 py-1 rounded-pill border {{ $show === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $label }}</button>
        @endforeach
        @if($stores->count() > 1)
            <div class="ml-auto w-56">
                <x-ui.select wire:model.live="storeFilter" id="mp_store_filter">
                    <option value="">All stores</option>
                    @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
                </x-ui.select>
            </div>
        @endif
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[720px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Item</th>
                    <th class="px-5 py-3 font-normal">Store</th>
                    <th class="px-5 py-3 font-normal">Price</th>
                    <th class="px-5 py-3 font-normal">Actual stock</th>
                    <th class="px-5 py-3 font-normal">Marketplace qty</th>
                    <th class="px-5 py-3 font-normal">Visible</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($items as $item)
                    @php($blockers = $item->marketplaceBlockers())
                    <tr class="hover:bg-canvas-soft/60 align-top">
                        <td class="px-5 py-3">
                            <a href="{{ route('portal.inventory.show', $item) }}" class="text-ink font-medium hover:text-primary-deep">{{ $item->name }}</a>
                            @if($item->product)
                                <p class="text-[11px] text-ink-mute">{{ $item->product->animalType?->name }}@if($item->product->displayName() !== $item->name) · catalog: {{ $item->product->displayName() }}@endif</p>
                            @endif
                            @if($blockers)
                                <p class="text-[11px] text-lemon mt-0.5">{{ $blockers[0] }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $item->branch?->name ?? '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</td>
                        <td class="px-5 py-3 tnum {{ $item->isLowStock() ? 'text-ruby font-semibold' : 'text-ink-secondary' }}">
                            {{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}
                            @if((float) $item->reserved_quantity > 0)<span class="block text-[11px] text-ink-mute">{{ rtrim(rtrim($item->reserved_quantity, '0'), '.') }} reserved</span>@endif
                        </td>
                        <td class="px-5 py-3 tnum text-ink">{{ rtrim(rtrim($item->marketplace_quantity, '0'), '.') }}</td>
                        <td class="px-5 py-3">{{ $item->is_marketplace_visible ? 'Yes' : 'No' }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$item->marketplace_status === 'active' ? 'success' : ($item->marketplace_status === 'out_of_stock' ? 'danger' : 'neutral')">{{ str_replace('_', ' ', $item->marketplace_status) }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <x-ui.button size="sm" variant="secondary" wire:click="edit({{ $item->id }})" target="edit({{ $item->id }})">Edit</x-ui.button>
                            @if($item->is_marketplace_visible)
                                <button wire:click="hide({{ $item->id }})" class="ml-2 text-[12px] text-ink-mute hover:text-ruby">Hide</button>
                            @endif
                        </td>
                    </tr>
                    @if($editingId === $item->id)
                        <tr class="bg-canvas-soft/60">
                            <td colspan="8" class="px-5 py-4">
                                <form wire:submit="save" class="flex flex-wrap items-end gap-4">
                                    <div class="w-44">
                                        <x-ui.input type="number" step="0.01" min="0" wire:model="quantity" label="Marketplace quantity" id="mp_qty_{{ $item->id }}" />
                                    </div>
                                    <button type="button" wire:click="exposeAll" class="text-[12px] text-primary hover:text-primary-deep pb-2.5">Expose all {{ rtrim(rtrim(number_format($item->availableQuantity(), 2, '.', ''), '0'), '.') }} available</button>
                                    <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary pb-2"><input type="checkbox" wire:model="visible" class="rounded border-hairline-input text-primary focus:ring-primary"> Visible</label>
                                    <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary pb-2"><input type="checkbox" wire:model="active" class="rounded border-hairline-input text-primary focus:ring-primary"> Active</label>
                                    <x-ui.button type="submit" size="sm" target="save">Save</x-ui.button>
                                    <x-ui.button size="sm" variant="ghost" wire:click="$set('editingId', null)">Cancel</x-ui.button>
                                </form>
                                @error('quantity') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="px-5 py-8 text-center text-ink-mute">No inventory items yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $items->links() }}
</div>
