@props(['label', 'value', 'sub' => null, 'tone' => 'ink'])

@php
    $toneClasses = [
        'ink' => 'text-ink',
        'primary' => 'text-primary',
        'ruby' => 'text-ruby',
        'lemon' => 'text-lemon',
    ];
@endphp

<div>
    <p class="text-[12px] uppercase tracking-wide text-ink-mute mb-1.5">{{ $label }}</p>
    <div class="flex items-baseline gap-2">
        <p class="text-[34px] font-light leading-none tracking-tight tnum {{ $toneClasses[$tone] ?? $toneClasses['ink'] }}">{{ $value }}</p>
        @if($sub)
            <span class="text-[13px] text-ink-mute tnum">{{ $sub }}</span>
        @endif
    </div>
</div>
