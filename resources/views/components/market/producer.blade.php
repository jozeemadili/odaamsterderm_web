{{-- "by Silverlands" with the producer's logo; falls back to the free-text brand. --}}
@props(['product', 'size' => 'sm'])
@php
    $maker = $product?->makerName();
    $logo = $product?->producer?->logoUrl();
    $img = $size === 'lg' ? 'h-8 w-8' : 'h-5 w-5';
@endphp
@if($maker)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 '.($size === 'lg' ? 'text-[14px]' : 'text-[12px]').' text-ink-mute']) }}>
        @if($logo)
            <img src="{{ $logo }}" alt="{{ $maker }} logo" class="{{ $img }} rounded object-contain bg-white border border-hairline">
        @endif
        <span>by <span class="font-medium text-ink-secondary">{{ $maker }}</span></span>
    </span>
@endif
