@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'bg-success-subtle text-success',
        'warning' => 'bg-canvas-cream text-lemon',
        'danger' => 'bg-ruby/10 text-ruby',
        'neutral' => 'bg-hairline text-ink-mute',
        'primary' => 'bg-primary-subtle text-primary-deep',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-pill text-[11px] font-medium tracking-wide ' . ($tones[$tone] ?? $tones['neutral'])]) }}>
    {{ $slot }}
</span>
