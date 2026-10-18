<div class="space-y-5">
    <a href="{{ route('market.home') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Marketplace</a>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] gap-5">
        <x-ui.card>
            <p class="text-[12px] uppercase tracking-wide text-ink-mute">{{ $store->merchant->business_name }}</p>
            <h1 class="text-[24px] font-light tracking-tight mt-1">{{ $store->name }}</h1>
            <p class="text-[14px] text-ink-secondary mt-1">{{ collect([$store->address, $store->city])->filter()->join(', ') }}</p>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                @if($distance !== null)
                    <x-ui.badge tone="primary">{{ \App\Support\GeoPoint::formatKm($distance) }} from you</x-ui.badge>
                @endif
                @if($store->phone)
                    <a href="tel:{{ $store->phone }}" class="text-[13px] text-primary hover:text-primary-deep">{{ $store->phone }}</a>
                @endif
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $store->latitude }},{{ $store->longitude }}" target="_blank" rel="noopener" class="text-[13px] text-primary hover:text-primary-deep">Directions &rarr;</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-5 text-[13px]">
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-ink-mute mb-1">Opening hours</p>
                    @if($store->opening_hours)
                        @foreach($store->openingHoursLines() as $day => $line)
                            <div class="flex justify-between text-ink-secondary"><span>{{ $day }}</span><span class="tnum">{{ $line }}</span></div>
                        @endforeach
                    @else
                        <p class="text-ink-mute">Not provided</p>
                    @endif
                </div>
                <div>
                    <p class="text-[11px] uppercase tracking-wide text-ink-mute mb-1">Pickup point</p>
                    <p class="text-ink-secondary">{{ $store->pickup_instructions ?: 'Ask at the counter.' }}</p>
                </div>
            </div>
        </x-ui.card>

        <div wire:ignore wire:key="store-map-{{ md5(json_encode($from?->toArray())) }}"
            x-data="storesMap({ stores: @js([['lat' => $store->latitude, 'lng' => $store->longitude, 'name' => $store->name, 'note' => $store->address]]), me: @js($from ? ['lat' => $from->latitude, 'lng' => $from->longitude] : null) })">
            <div x-ref="map" class="h-72 lg:h-full min-h-[288px] rounded-xl border border-hairline z-0"></div>
        </div>
    </div>

    @error('cart') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror
    @if($cartMessage)
        <div class="rounded-lg bg-success-subtle text-success px-4 py-2.5 text-[14px] flex justify-between">
            <span>{{ $cartMessage }}</span>
            <a href="{{ route('market.cart') }}" class="font-medium underline">View cart &rarr;</a>
        </div>
    @endif

    <x-ui.card padding="p-0">
        <div class="px-4 py-3 border-b border-hairline"><h2 class="text-[16px] font-light">Products from this store</h2></div>
        <div class="divide-y divide-hairline">
            @forelse($offers as $offer)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 {{ $offer->is_available ? '' : 'text-ink-mute' }}">
                    <a href="{{ route('market.product', $offer->product->slug) }}" class="hover:text-primary-deep flex items-center gap-3">
                        @if($photo = $offer->photoUrl())
                            <img src="{{ $photo }}" alt="" class="h-12 w-12 shrink-0 rounded object-cover border border-hairline" loading="lazy">
                        @else
                            <span class="h-12 w-12 shrink-0 rounded bg-canvas-soft border border-hairline flex items-center justify-center"><x-icon.inventory class="h-5 w-5 text-ink-mute/50" /></span>
                        @endif
                        <div>
                        <p class="font-medium text-[14px] {{ $offer->is_available ? 'text-ink' : '' }}">{{ $offer->product->displayName() }}</p>
                        <x-market.producer :product="$offer->product" />
                        <p class="text-[12px] text-ink-mute">{{ $offer->is_available ? rtrim(rtrim((string) $offer->marketplace_quantity, '0'), '.').' '.$offer->product->unit.' available' : 'Out of stock' }}</p>
                        </div>
                    </a>
                    <div class="flex items-center gap-3">
                        <p class="tnum font-medium">TZS {{ number_format($offer->unit_price) }}</p>
                        @if($offer->is_available)
                            <input type="number" min="1" step="1" wire:model="quantities.{{ $offer->id }}" placeholder="1" aria-label="Quantity" class="w-16 rounded-sm border border-hairline-input px-2 py-1 text-[13px] tnum">
                            <x-ui.button size="sm" wire:click="addToCart({{ $offer->id }})" target="addToCart({{ $offer->id }})">Add</x-ui.button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-ink-mute text-[14px]">This store has no products online right now.</p>
            @endforelse
        </div>
    </x-ui.card>
</div>
