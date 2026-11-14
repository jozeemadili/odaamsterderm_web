{{-- Expects $product from MarketplaceSearch::products() (with min_price, sellers_count, nearest_km) --}}
<article class="w-full group relative flex flex-col overflow-hidden rounded-xl bg-canvas border border-hairline hover:border-primary/40 hover:shadow-[0_8px_24px_rgba(42,10,15,0.08)] transition">
    <x-market.photo-carousel :photos="$product->imageUrls()" :alt="$product->displayName()" :href="route('market.product', $product->slug)" hover-flip class="aspect-square" />
    <a href="{{ route('market.product', $product->slug) }}" class="flex flex-1 flex-col p-4">
        <p class="text-[11px] uppercase tracking-wide text-ink-mute truncate">{{ collect([$product->animalType?->name, $product->category?->name])->filter()->join(' · ') }}</p>
        <h2 class="text-[15px] font-medium text-ink mt-1 leading-snug line-clamp-2 group-hover:text-primary-deep">{{ $product->displayName() }}</h2>
        <x-market.producer :product="$product" class="mt-1" />
        <div class="mt-auto pt-3 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1 sm:gap-2">
            <div>
                @if($product->min_price !== null)
                    <p class="text-[11px] text-ink-mute">from</p>
                    @prices
                        <p class="text-[16px] sm:text-[18px] font-semibold tnum text-ink whitespace-nowrap">TZS {{ number_format($product->min_price) }}</p>
                    @else
                        <p class="text-[13px] font-semibold text-success whitespace-nowrap">In stock</p>
                    @endprices
                @elseif($product->sellers_count)
                    <p class="text-[13px] text-ruby font-medium">Out of stock</p>
                @else
                    <p class="text-[13px] text-ink-mute font-medium">Not in stores yet</p>
                @endif
            </div>
            <div class="sm:text-right text-[12px] text-ink-mute">
                @if($product->sellers_count)
                    <p>{{ $product->sellers_count }} {{ \Illuminate\Support\Str::plural('seller', $product->sellers_count) }}</p>
                @endif
                @if($product->nearest_km !== null)
                    <x-market.distance :km="$product->nearest_km" prefix="Nearest" class="mt-1" />
                @endif
            </div>
        </div>
    </a>
</article>
