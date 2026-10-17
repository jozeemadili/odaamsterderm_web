<div class="space-y-4">
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wide text-ink-mute">Marketplace order</p>
                <h2 class="text-[24px] font-light tracking-tight tnum">{{ $order->reference }}</h2>
                <p class="text-[13px] text-ink-mute">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
            </div>
            <div class="flex items-center gap-3">
                @include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])
                <a href="{{ route('admin.orders.index') }}" class="text-[13px] text-primary">&larr; Orders</a>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5 text-[13px]">
            <div><p class="text-ink-mute mb-1">Customer</p><p class="font-medium">{{ $order->customer_name }}</p><p class="text-ink-mute">{{ $order->customer_phone }} · {{ $order->customer?->email }}</p></div>
            <div><p class="text-ink-mute mb-1">Seller</p><a href="{{ route('admin.merchants.show', $order->merchant) }}" class="font-medium text-primary">{{ $order->merchant->business_name }}</a><p class="text-ink-mute">{{ $order->branch->name }}</p></div>
            <div><p class="text-ink-mute mb-1">Notes</p><p>{{ $order->customer_note ?: '—' }}</p>@if($order->status_reason)<p class="text-ruby">{{ $order->status_reason }}</p>@endif</div>
        </div>
    </x-ui.card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui.card padding="p-0" class="lg:col-span-2">
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-hairline">
                    @foreach($order->items as $item)
                        <tr><td class="px-5 py-2.5">{{ $item->name }}</td><td class="px-5 py-2.5 tnum">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} × {{ number_format($item->unit_price) }}</td><td class="px-5 py-2.5 tnum text-right">TZS {{ number_format($item->line_total) }}</td></tr>
                    @endforeach
                    <tr><td colspan="2" class="px-5 py-3 font-medium">Total</td><td class="px-5 py-3 tnum text-right font-medium">TZS {{ number_format($order->total) }}</td></tr>
                </tbody>
            </table>
            @if($order->sale)<p class="px-5 py-3 border-t border-hairline text-[12px] text-ink-mute">Recorded as online sale #{{ $order->sale->id }}.</p>@endif
        </x-ui.card>
        <x-ui.card>
            <h3 class="text-[15px] text-ink-secondary mb-3">Timeline</h3>
            <ol class="space-y-2 text-[13px]">
                @foreach($order->events as $event)
                    <li><strong class="font-medium">{{ \App\Models\Order::LABELS[$event->status] ?? $event->status }}</strong>
                        <span class="text-ink-mute">· {{ $event->created_at->format('d M, H:i') }}{{ $event->user ? ' · '.$event->user->name : '' }}</span>
                        @if($event->note)<span class="block text-ink-mute">{{ $event->note }}</span>@endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>
</div>
