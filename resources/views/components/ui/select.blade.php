@props(['label' => null, 'error' => null])

<div>
    @if($label)
        <label @if($attributes->get('id')) for="{{ $attributes->get('id') }}" @endif class="block text-[13px] text-ink-mute mb-1.5">{{ $label }}</label>
    @endif

    <select {{ $attributes->merge(['class' => 'w-full rounded-sm border border-hairline-input bg-canvas text-ink text-[15px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition']) }}>
        {{ $slot }}
    </select>

    @if($error)
        <p class="text-ruby text-[12px] mt-1">{{ $error }}</p>
    @endif
</div>
