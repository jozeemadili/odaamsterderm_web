<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-64"><x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search store, seller or city…" id="store_search" /></div>
        @foreach(['all' => 'All', 'live' => 'Live on marketplace', 'suspended' => 'Suspended', 'no_location' => 'No map location', 'marketplace_off' => 'Marketplace off'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')" class="text-[12px] px-3 py-1 rounded-pill border {{ $filter === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $label }}</button>
        @endforeach
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[860px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Store</th>
                    <th class="px-5 py-3 font-normal">Seller</th>
                    <th class="px-5 py-3 font-normal">Location</th>
                    <th class="px-5 py-3 font-normal">Listings</th>
                    <th class="px-5 py-3 font-normal">Open orders</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($stores as $store)
                    <tr class="hover:bg-canvas-soft/60 align-top">
                        <td class="px-5 py-3">
                            <p class="text-ink font-medium">{{ $store->name }}</p>
                            <p class="text-[11px] text-ink-mute">{{ collect([$store->address, $store->city])->filter()->join(', ') }}</p>
                        </td>
                        <td class="px-5 py-3"><a href="{{ route('admin.merchants.show', $store->merchant) }}" class="text-primary hover:text-primary-deep">{{ $store->merchant->business_name }}</a>
                            @unless($store->merchant->canSellOnMarketplace())<span class="block text-[11px] text-lemon">seller not approved</span>@endunless
                        </td>
                        <td class="px-5 py-3">
                            @if($store->hasLocation())
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $store->latitude }},{{ $store->longitude }}" target="_blank" rel="noopener" class="text-primary text-[12px]">On map &rarr;</a>
                            @else
                                <x-ui.badge tone="warning">missing</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 tnum">{{ $store->listed_count }} @unless($store->is_marketplace_enabled)<span class="block text-[11px] text-ink-mute">marketplace off</span>@endunless</td>
                        <td class="px-5 py-3 tnum">{{ $store->open_orders }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$store->isActive() ? 'success' : 'danger'">{{ $store->isActive() ? 'Active' : 'Suspended' }}</x-ui.badge>
                            @if($store->suspension_reason)<p class="text-[11px] text-ruby mt-1">{{ $store->suspension_reason }}</p>@endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if($canManage)
                                @if($store->isActive())
                                    <button wire:click="$set('suspendingId', {{ $store->id }})" class="text-[12px] text-ruby hover:opacity-80">Suspend</button>
                                @else
                                    <button wire:click="reactivate({{ $store->id }})" wire:confirm="Reactivate {{ $store->name }}?" class="text-[12px] text-primary hover:opacity-80">Reactivate</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @if($suspendingId === $store->id)
                        <tr class="bg-canvas-soft/60">
                            <td colspan="7" class="px-5 py-4">
                                <form wire:submit="suspend" class="flex flex-wrap items-end gap-3">
                                    <div class="flex-1 min-w-[280px]">
                                        <x-ui.input wire:model="reason" label="Reason (sent to the seller)" id="suspend_reason" placeholder="e.g. Selling unregistered veterinary drugs" :error="$errors->first('reason')" />
                                    </div>
                                    <x-ui.button type="submit" variant="danger" size="sm" target="suspend">Suspend store</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="$set('suspendingId', null)">Cancel</x-ui.button>
                                </form>
                                @if($store->open_orders)
                                    <p class="text-[12px] text-lemon mt-2">{{ $store->open_orders }} open order(s) stay with the seller to complete or reject.</p>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No stores found.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $stores->links() }}
</div>
