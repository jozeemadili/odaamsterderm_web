<div class="space-y-5">
    <a href="{{ route('market.home') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; All products</a>

    <x-ui.card>
        <div class="flex flex-col sm:flex-row gap-5">
            <div class="h-32 w-32 shrink-0 rounded-lg bg-canvas-soft border border-hairline flex items-center justify-center overflow-hidden">
                @if($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $product->displayName() }}" class="h-full w-full object-cover">
                @else
                    <x-icon.inventory class="h-10 w-10 text-ink-mute/50" />
                @endif
            </div>
            <div class="flex-1">
                <p class="text-[12px] uppercase tracking-wide text-ink-mute">{{ $product->animalType?->fullName() }} · {{ $product->category?->fullName() }}</p>
                <h1 class="text-[24px] font-light tracking-tight mt-1">{{ $product->displayName() }}</h1>
                <x-market.producer :product="$product" size="lg" class="mt-2" />
                <p class="text-[13px] text-ink-mute mt-1">
                    Sold per {{ $product->unit }}@if($product->producer?->country) · Made in {{ $product->producer->country }}@endif
                </p>
                @if($product->producer?->description)
                    <p class="text-[12px] text-ink-mute mt-1 max-w-2xl">About {{ $product->producer->name }}: {{ $product->producer->description }}</p>
                @endif
                @if($product->description)
                    <p class="text-[14px] text-ink-secondary mt-3 max-w-2xl">{{ $product->description }}</p>
                @endif
            </div>
        </div>
    </x-ui.card>

    @error('cart') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror
    @if($cartMessage)
        <div class="rounded-lg bg-success-subtle text-success px-4 py-2.5 text-[14px] flex justify-between">
            <span>{{ $cartMessage }}</span>
            <a href="{{ route('market.cart') }}" class="font-medium underline">View cart &rarr;</a>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-[18px] font-light tracking-tight">Compare {{ $offers->count() }} {{ \Illuminate\Support\Str::plural('seller', $offers->count()) }}</h2>
        <div class="flex items-center gap-1 bg-canvas rounded-pill p-1 border border-hairline text-[13px]">
            @foreach(['nearest' => 'Nearest', 'price' => 'Lowest price', 'availability' => 'Most available'] as $key => $label)
                <button wire:click="$set('sort', '{{ $key }}')" @disabled($key === 'nearest' && ! $from)
                    class="px-3 py-1.5 rounded-pill transition disabled:opacity-40 {{ $sort === $key ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-5">
        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-[14px] min-w-[680px]">
                    <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                        <tr>
                            <th class="px-4 py-3 font-normal">Seller</th>
                            <th class="px-4 py-3 font-normal">Distance</th>
                            <th class="px-4 py-3 font-normal">Price</th>
                            <th class="px-4 py-3 font-normal">Available</th>
                            <th class="px-4 py-3 font-normal">Status</th>
                            <th class="px-4 py-3 font-normal"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($offers as $offer)
                            <tr class="{{ $offer->is_available ? '' : 'text-ink-mute' }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @if($photo = $offer->photoUrl())
                                            <a href="{{ $photo }}" target="_blank" rel="noopener" title="Photo from {{ $offer->merchant->business_name }}"><img src="{{ $photo }}" alt="" class="h-10 w-10 shrink-0 rounded object-cover border border-hairline"></a>
                                        @endif
                                        <div>
                                            <a href="{{ route('market.store', $offer->branch_id) }}" class="font-medium text-ink hover:text-primary-deep">{{ $offer->merchant->business_name }}</a>
                                            <p class="text-[12px] text-ink-mute">{{ $offer->branch->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 tnum">{{ \App\Support\GeoPoint::formatKm($offer->distance_km) ?? '—' }}</td>
                                <td class="px-4 py-3 tnum font-medium">TZS {{ number_format($offer->unit_price) }}</td>
                                <td class="px-4 py-3 tnum">{{ $offer->is_available ? rtrim(rtrim((string) $offer->marketplace_quantity, '0'), '.').' '.\Illuminate\Support\Str::plural($product->unit, (int) $offer->marketplace_quantity) : '0' }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :tone="$offer->is_available ? 'success' : 'danger'">{{ $offer->is_available ? 'Available' : 'Out of stock' }}</x-ui.badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($offer->is_available)
                                        <div class="inline-flex items-center gap-2">
                                            <input type="number" min="1" step="1" max="{{ (float) $offer->marketplace_quantity }}" wire:model="quantities.{{ $offer->id }}" placeholder="1" aria-label="Quantity"
                                                class="w-16 rounded-sm border border-hairline-input px-2 py-1 text-[13px] tnum">
                                            <x-ui.button size="sm" wire:click="addToCart({{ $offer->id }})" target="addToCart({{ $offer->id }})">Add</x-ui.button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-ink-mute">No sellers are offering this product right now.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($from)
                <p class="px-4 py-3 text-[12px] text-ink-mute border-t border-hairline">Set your location (top of the page) to see how far each store is.</p>
            @endunless
        </x-ui.card>

        @if($mapStores->isNotEmpty())
            <div wire:ignore wire:key="map-{{ md5(json_encode([$mapStores, $from?->toArray()])) }}"
                x-data="storesMap({ stores: @js($mapStores), me: @js($from ? ['lat' => $from->latitude, 'lng' => $from->longitude] : null) })">
                <div x-ref="map" class="h-80 lg:h-full min-h-[320px] rounded-xl border border-hairline z-0"></div>
            </div>
        @endif
    </div>

    <p class="text-[12px] text-ink-mute">Order online and collect at the store — you pay at pickup. Prices and quantities are rechecked when you place the order.</p>
</div>
