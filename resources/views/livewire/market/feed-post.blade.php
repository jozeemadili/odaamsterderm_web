{{-- A product as a feed post, like the mobile app: maker on top, a big
     swipeable photo, Ask AI / share / View, then price, name and packs.
     Expects $product from MarketplaceSearch::products(). --}}
@php
    $maker = $product->makerName() ?? 'Amsterdam Digital';
    $logo = $product->producer?->logoUrl();
    $initials = collect(preg_split('/\s+/', trim($maker)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $subtitle = collect([$product->category?->name ?? $product->animalType?->name, $product->nearest_km !== null ? \App\Support\GeoPoint::formatKm((float) $product->nearest_km) : null])->filter()->join(' · ');
    $url = route('market.product', $product->slug);
    $packs = $product->packRange();
    $uses = \App\Models\ProductAttribute::useTags($product->use_cases);
@endphp
<article class="flex flex-col overflow-hidden rounded-[18px] bg-canvas border border-hairline hover:shadow-[0_10px_28px_rgba(42,10,15,0.08)] transition">
    {{-- Who makes it, like the account that posted it --}}
    <div class="flex items-center gap-2.5 px-3 py-2.5">
        <span class="h-9 w-9 shrink-0 rounded-full p-[2px] bg-gradient-to-tr from-leaf to-primary">
            <span class="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-white p-[2px]">
                @if($logo)
                    <img src="{{ $logo }}" alt="" class="h-full w-full rounded-full object-contain">
                @else
                    <span class="text-[11px] font-extrabold text-primary">{{ $initials }}</span>
                @endif
            </span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-[13.5px] font-bold text-ink">{{ $maker }}</span>
            @if($subtitle !== '')
                <span class="block truncate text-[11.5px] text-ink-mute">{{ $subtitle }}</span>
            @endif
        </span>
        @if($product->min_price !== null)
            <span class="shrink-0 rounded-pill bg-success-subtle px-2 py-0.5 text-[11px] font-semibold text-success">Available</span>
        @endif
    </div>

    <x-market.photo-carousel :photos="$product->imageUrls()" :alt="$product->displayName()" :href="$url" hover-flip class="aspect-square" />

    {{-- Actions --}}
    <div class="flex items-center gap-1 px-2 pt-1.5" x-data>
        @if(\App\Services\Ai\AiProductSearch::enabled())
            <a href="{{ route('market.home', ['ask' => 'Tell me about '.$product->displayName().': what it is for and how to use it.']) }}"
                title="Ask AI about this" aria-label="Ask AI about {{ $product->displayName() }}"
                class="flex h-10 w-10 items-center justify-center rounded-full text-primary hover:bg-primary-subtle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="h-[22px] w-[22px]"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 1.9 1.9.8-1.9.8L19 20.4l-.8-1.9-1.9-.8 1.9-.8z"/></svg>
            </a>
        @endif
        <button type="button" title="Share" aria-label="Share {{ $product->displayName() }}"
            x-on:click="navigator.share ? navigator.share({ title: @js($product->displayName()), url: @js($url) }).catch(() => {}) : navigator.clipboard.writeText(@js($url)).then(() => { $el.dataset.copied = '1'; setTimeout(() => delete $el.dataset.copied, 1500) })"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-ink hover:bg-canvas-soft data-[copied]:text-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="h-[21px] w-[21px]"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
        </button>
        <a href="{{ $url }}" class="ml-auto rounded-pill bg-leaf px-4 py-1.5 text-[13.5px] font-bold text-ink hover:bg-leaf-deep">View</a>
    </div>

    {{-- Caption --}}
    <a href="{{ $url }}" class="block px-3.5 pb-4 pt-1.5">
        @if($product->min_price !== null)
            <span class="block font-display text-[18px] font-bold tnum text-ink">TZS {{ number_format($product->min_price) }}</span>
        @elseif($product->sellers_count)
            <span class="block text-[13px] font-semibold text-ruby">Out of stock</span>
        @else
            <span class="block text-[13px] font-semibold text-ink-mute">Not in stores yet</span>
        @endif
        <span class="mt-1 block text-[14px] leading-snug line-clamp-2"><span class="font-bold text-ink">{{ $product->displayName() }}</span>@if($packs)<span class="text-ink-mute">&nbsp; {{ $packs }}</span>@endif</span>
        @if($uses)
            <span class="mt-2 flex flex-wrap gap-1.5">
                @foreach(array_slice($uses, 0, 3) as $u)
                    <span title="{{ $u['label_sw'] }}" class="rounded-pill bg-leaf/15 px-2 py-0.5 text-[11.5px] font-semibold text-leaf-deep">{{ $u['label'] }}</span>
                @endforeach
                @if(count($uses) > 3)<span class="rounded-pill bg-canvas-soft px-2 py-0.5 text-[11.5px] font-semibold text-ink-mute">+{{ count($uses) - 3 }}</span>@endif
            </span>
        @endif
    </a>
</article>
