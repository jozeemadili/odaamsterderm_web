<div class="flex items-center gap-3">
    <a href="{{ route('market.orders') }}" class="relative text-white/80 hover:text-white" title="My orders">
        Orders
        @if($unread > 0)
            <span class="absolute -top-2 -right-3 h-4 min-w-[16px] px-1 rounded-full bg-ruby text-white text-[10px] leading-4 text-center">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
    <a href="{{ route('market.cart') }}" class="relative inline-flex items-center gap-1 rounded-pill border border-white/20 px-3 py-1.5 text-white/90 hover:border-primary-soft">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M3 4h2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L20 8H6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="19.5" r="1.3" fill="currentColor"/><circle cx="17" cy="19.5" r="1.3" fill="currentColor"/></svg>
        Cart
        @if($count > 0)
            <span class="h-4 min-w-[16px] px-1 rounded-full bg-primary text-ink text-[10px] leading-4 text-center font-medium">{{ $count }}</span>
        @endif
    </a>
</div>
