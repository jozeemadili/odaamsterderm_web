@props(['method', 'size' => 'h-6 w-6 text-[10px]'])

@php
    $palette = [
        'cash' => ['bg' => '#DCF2E6', 'text' => '#1F8A57', 'label' => 'TZS'],
        'mpesa' => ['bg' => '#DFF5E1', 'text' => '#1E7E34', 'label' => 'M'],
        'mixx_yas' => ['bg' => '#FDE9D9', 'text' => '#C24E00', 'label' => 'Mx'],
        'halopesa' => ['bg' => '#FBE3E8', 'text' => '#C21E4A', 'label' => 'H'],
        'tpesa' => ['bg' => '#E1ECFB', 'text' => '#1A56B0', 'label' => 'T'],
        'bank' => ['bg' => '#E9EDF3', 'text' => '#01162F', 'label' => 'Bk'],
        'card' => ['bg' => '#F3E6C8', 'text' => '#96702F', 'label' => 'Cd'],
    ];

    $colors = $palette[$method->slug] ?? ['bg' => '#E9EDF3', 'text' => '#01162F', 'label' => mb_substr($method->name, 0, 2)];
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full font-semibold shrink-0 $size"]) }}
    style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }};"
>{{ $colors['label'] }}</span>
