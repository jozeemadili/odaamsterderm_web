@props(['padding' => 'p-6'])

@php
    $scroll = $padding === 'p-0' ? 'overflow-x-auto' : '';
@endphp

<div {{ $attributes->merge(['class' => "bg-canvas border border-hairline rounded-lg shadow-[0_1px_3px_rgba(90,16,25,0.06)] $padding $scroll"]) }}>
    {{ $slot }}
</div>
