@props(['size' => 'md'])
{{-- The Amsterdam Digital lockup: logo, green "Amsterdam" and maroon
     "Digital" on one white badge, so both brand colours stay readable on
     the dark maroon bars. --}}
@php
    [$logo, $text, $pad] = match ($size) {
        'sm' => ['h-6', 'text-[15px]', 'pl-1 pr-2.5 py-1'],
        default => ['h-7', 'text-[17px]', 'pl-1.5 pr-3 py-1'],
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-lg bg-white shadow-sm $pad"]) }}>
    <img src="{{ asset('images/amsterdam-mark.png') }}" alt="" class="{{ $logo }} w-auto">
    <span class="{{ $text }} font-semibold tracking-tight leading-none whitespace-nowrap"><span class="text-success">Amsterdam</span><span class="text-primary"> Digital</span></span>
</span>
