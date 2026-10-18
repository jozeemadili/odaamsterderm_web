{{-- "📍 1.2 km" badge: how far a store is from the customer's location. --}}
@props(['km', 'prefix' => null])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-pill bg-primary-subtle/60 text-ink px-2 py-0.5 text-[12px] font-medium tnum']) }}>
    <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="2"/></svg>
    {{ $prefix ? $prefix.' ' : '' }}{{ \App\Support\GeoPoint::formatKm((float) $km) }}
</span>
