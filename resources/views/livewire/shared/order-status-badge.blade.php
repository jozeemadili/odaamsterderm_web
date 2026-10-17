@php
    $tone = match ($status) {
        'pending' => 'warning',
        'accepted', 'preparing' => 'primary',
        'ready_for_pickup', 'completed' => 'success',
        default => 'danger',
    };
@endphp
<x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
