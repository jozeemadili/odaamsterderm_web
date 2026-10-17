<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] uppercase tracking-wide text-ink-mute">Marketplace order</p>
                <h2 class="text-[24px] font-light tracking-tight tnum">{{ $order->reference }}</h2>
                <p class="text-[13px] text-ink-mute">Placed {{ $order->created_at->format('d M Y, H:i') }} · pickup at {{ $order->branch->name }}</p>
            </div>
            <div class="flex items-center gap-3">
                @include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])
                <a href="{{ route('portal.orders.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Orders</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5 text-[13px]">
            <div>
                <p class="text-ink-mute mb-1">Customer</p>
                <p class="text-ink font-medium">{{ $order->customer_name }}</p>
                @if($order->customer_phone)<a href="tel:{{ $order->customer_phone }}" class="text-primary">{{ $order->customer_phone }}</a>@endif
            </div>
            <div class="md:col-span-2">
                <p class="text-ink-mute mb-1">Customer note</p>
                <p class="text-ink-secondary">{{ $order->customer_note ?: '—' }}</p>
            </div>
        </div>

        @if($order->nextSellerStatuses())
            <div class="mt-5 pt-5 border-t border-hairline">
                @if($rejecting)
                    <form wire:submit="advance('rejected')" class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[260px]">
                            <x-ui.input wire:model="reason" label="Reason (sent to the customer)" id="reject_reason" placeholder="e.g. Out of this brand today" :error="$errors->first('reason')" />
                        </div>
                        <x-ui.button type="submit" variant="danger" size="sm" target="advance">Reject order</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="$set('rejecting', false)">Back</x-ui.button>
                    </form>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($order->nextSellerStatuses() as $next)
                            @if($next === 'rejected')
                                <x-ui.button variant="secondary" size="sm" wire:click="$set('rejecting', true)">Reject</x-ui.button>
                            @else
                                <x-ui.button size="sm" wire:click="advance('{{ $next }}')" target="advance('{{ $next }}')"
                                    wire:confirm="{{ $next === 'completed' ? 'Confirm the customer collected and paid? Stock will be deducted and the sale recorded.' : '' }}">
                                    {{ ['accepted' => 'Accept order', 'preparing' => 'Start preparing', 'ready_for_pickup' => 'Mark ready for pickup', 'completed' => 'Collected — complete'][$next] }}
                                </x-ui.button>
                            @endif
                        @endforeach
                    </div>
                    @error('reason') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
                @endif
            </div>
        @endif
    </x-ui.card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui.card padding="p-0" class="lg:col-span-2">
            <div class="px-5 py-3 border-b border-hairline"><h3 class="text-[15px] text-ink-secondary">Items</h3></div>
            <table class="w-full text-[13px]">
                <tbody class="divide-y divide-hairline">
                    @foreach($order->items as $item)
                        <tr>
                            <td class="px-5 py-2.5">{{ $item->name }}
                                @if($item->inventoryItem)<span class="block text-[11px] text-ink-mute">{{ rtrim(rtrim((string) $item->inventoryItem->quantity_on_hand, '0'), '.') }} on hand · {{ rtrim(rtrim((string) $item->inventoryItem->reserved_quantity, '0'), '.') }} reserved</span>@endif
                            </td>
                            <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}</td>
                            <td class="px-5 py-2.5 tnum text-ink-secondary">@ {{ number_format($item->unit_price) }}</td>
                            <td class="px-5 py-2.5 tnum text-right">TZS {{ number_format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="3" class="px-5 py-3 font-medium">Total (customer pays at pickup)</td><td class="px-5 py-3 tnum text-right font-medium">TZS {{ number_format($order->total) }}</td></tr>
                </tbody>
            </table>
            @if($order->sale)
                <p class="px-5 py-3 border-t border-hairline text-[12px] text-ink-mute">Recorded as online sale #{{ $order->sale->id }} — included in your sales reports.</p>
            @endif
        </x-ui.card>

        <x-ui.card>
            <h3 class="text-[15px] text-ink-secondary mb-3">Timeline</h3>
            <ol class="space-y-2 text-[13px]">
                @foreach($order->events as $event)
                    <li>
                        <strong class="font-medium">{{ \App\Models\Order::LABELS[$event->status] ?? $event->status }}</strong>
                        <span class="text-ink-mute">· {{ $event->created_at->format('d M, H:i') }}{{ $event->user ? ' · '.$event->user->name : '' }}</span>
                        @if($event->note)<span class="block text-ink-mute">{{ $event->note }}</span>@endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>
</div>
