<div class="space-y-4">
    <div class="flex justify-end">
        <x-ui.button wire:click="$toggle('showDamageForm')">
            {{ $showDamageForm ? 'Cancel' : '+ Report damaged product' }}
        </x-ui.button>
    </div>

    @if($showDamageForm)
        <x-ui.card>
            <form wire:submit="reportDamage" class="space-y-4">
                <div class="relative">
                    <x-ui.input wire:model.live.debounce.300ms="itemSearch" label="Product" placeholder="Search by name…" id="damage_item_search" autocomplete="off" />
                    @error('inventory_item_id') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror

                    @if(count($itemMatches) > 0)
                        <div class="mt-2 border border-hairline rounded-md divide-y divide-hairline overflow-hidden">
                            @foreach($itemMatches as $match)
                                <button
                                    type="button"
                                    wire:click="selectItem({{ $match['id'] }}, '{{ addslashes($match['name']) }}')"
                                    class="w-full flex items-center justify-between px-3 py-2 text-left text-[13px] hover:bg-canvas-soft"
                                >
                                    <span class="text-ink font-medium">{{ $match['name'] }}</span>
                                    <span class="text-ink-mute tnum">{{ rtrim(rtrim(number_format($match['quantity_on_hand'], 2, '.', ''), '0'), '.') }} {{ $match['unit'] }} in stock</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input type="number" step="0.01" wire:model="quantity" label="Quantity damaged" id="damage_quantity" />
                    <x-ui.input wire:model="description" label="Description (optional)" id="damage_description" placeholder="e.g. dropped, expired, water damage" />
                </div>

                <div>
                    <label class="block text-[13px] text-ink-mute mb-1.5">Photo (optional)</label>
                    <input type="file" wire:model="photo" accept="image/*" class="text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                    @error('photo') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="photo" class="text-[12px] text-ink-mute mt-1">Uploading…</div>
                    @if($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="mt-2 h-20 w-20 rounded object-cover border border-hairline">
                    @endif
                </div>

                <x-ui.button type="submit" target="reportDamage">Save damage report</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Low stock</h2>
            @forelse($lowStockItems as $item)
                <div class="flex items-center justify-between py-2 border-b border-hairline last:border-0 text-[13px]">
                    <div>
                        <span class="text-ink-secondary font-medium">{{ $item->name }}</span>
                        <span class="text-ink-mute"> &middot; {{ $item->branch?->name ?? 'Unassigned' }}</span>
                    </div>
                    <span class="tnum text-ruby font-medium">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }} left</span>
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">Nothing low on stock right now.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Expiring soon</h2>
            @forelse($expiringSoonItems as $item)
                <div class="flex items-center justify-between py-2 border-b border-hairline last:border-0 text-[13px]">
                    <div>
                        <span class="text-ink-secondary font-medium">{{ $item->name }}</span>
                        <span class="text-ink-mute"> &middot; {{ $item->branch?->name ?? 'Unassigned' }}</span>
                    </div>
                    <span class="tnum text-lemon font-medium">{{ $item->expiry_date->format('d M Y') }}</span>
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">Nothing expiring within a month.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card class="lg:col-span-2" padding="p-0">
            <div class="px-6 pt-5 pb-1">
                <h2 class="text-[15px] text-ink-secondary">Damaged products</h2>
            </div>
            <table class="w-full text-[13px] mt-3">
                <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                    <tr>
                        <th class="px-6 py-3 font-normal"></th>
                        <th class="px-5 py-3 font-normal">Product</th>
                        <th class="px-5 py-3 font-normal">Qty</th>
                        <th class="px-5 py-3 font-normal">Description</th>
                        <th class="px-5 py-3 font-normal">Store</th>
                        <th class="px-5 py-3 font-normal">Reported by</th>
                        <th class="px-5 py-3 font-normal">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($damagedItems as $report)
                        <tr class="hover:bg-canvas-soft/60">
                            <td class="px-6 py-2.5">
                                @if($report->photo())
                                    <img src="{{ $report->photo()->getUrl() }}" alt="" class="h-9 w-9 rounded object-cover border border-hairline">
                                @else
                                    <span class="h-9 w-9 rounded bg-canvas-soft border border-hairline flex items-center justify-center text-ink-mute text-[10px]">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-ink font-medium">{{ $report->inventoryItem?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5 tnum text-ruby font-medium">{{ rtrim(rtrim($report->quantity, '0'), '.') }}</td>
                            <td class="px-5 py-2.5 text-ink-secondary">{{ $report->description ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-ink-secondary">{{ $report->branch?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-ink-secondary">{{ $report->reportedBy?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-ink-mute tnum">{{ $report->reported_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No damage reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>
</div>
