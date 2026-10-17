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

    <x-ui.card padding="p-0">
        <div class="px-4 py-3 border-b border-hairline"><h2 class="text-[16px] font-light">Products from this store</h2></div>
        <div class="divide-y divide-hairline">
            @forelse($offers as $offer)
                <a href="{{ route('market.product', $offer->product->slug) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-canvas-soft {{ $offer->is_available ? '' : 'text-ink-mute' }}">
                    <div>
                        <p class="font-medium text-[14px] {{ $offer->is_available ? 'text-ink' : '' }}">{{ $offer->product->displayName() }}</p>
                        <p class="text-[12px] text-ink-mute">{{ $offer->is_available ? rtrim(rtrim((string) $offer->marketplace_quantity, '0'), '.').' '.$offer->product->unit.' available' : 'Out of stock' }}</p>
                    </div>
                    <p class="tnum font-medium">TZS {{ number_format($offer->unit_price) }}</p>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-ink-mute text-[14px]">This store has no products online right now.</p>
            @endforelse
        </div>
    </x-ui.card>
</div>
