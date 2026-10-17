@props([
    'variant' => 'primary',
    'type' => 'button',
    'target' => null,
    'size' => 'md',
    'disabled' => false,
])

@php
    $variants = [
        'primary' => 'bg-primary text-ink font-medium hover:bg-primary-deep active:bg-primary-press shadow-[0_1px_3px_rgba(0,55,112,0.08)]',
        'secondary' => 'bg-canvas text-ink border border-ink/20 hover:bg-canvas-soft',
        'dark' => 'bg-brand-dark text-white hover:bg-ink',
        'danger' => 'bg-ruby text-white hover:opacity-90',
        'ghost' => 'bg-transparent text-ink-mute hover:text-ink hover:bg-hairline/60 shadow-none',
    ];

    $sizes = [
        'md' => 'px-4 py-2 text-[15px]',
        'sm' => 'px-3.5 py-1.5 text-[13px]',
    ];

    $base = 'inline-flex items-center justify-center gap-2 rounded-pill font-normal leading-none transition disabled:opacity-60 disabled:cursor-not-allowed';
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $classes]) }}
    wire:loading.attr="disabled"
    wire:loading.class="cursor-wait"
    @if($target) wire:target="{{ $target }}" @endif
    @if($disabled) disabled @endif
>
    <svg
        wire:loading
        @if($target) wire:target="{{ $target }}" @endif
        class="spinner h-3.5 w-3.5 shrink-0"
        viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
    >
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-opacity="0.25" />
        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span>{{ $slot }}</span>
</button>
