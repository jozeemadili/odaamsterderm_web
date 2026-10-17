<div class="max-w-3xl space-y-5" wire:poll.30s>
    <a href="{{ route('market.orders') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; My orders</a>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[12px] uppercase tracking-wide text-ink-mute">Order reference</p>
                <h1 class="text-[26px] font-light tracking-tight tnum">{{ $order->reference }}</h1>
                <p class="text-[13px] text-ink-mute">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
            </div>
            @include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])
        </div>

        @if($order->status === 'ready_for_pickup')
            <div class="mt-4 rounded-lg bg-success-subtle text-success px-4 py-3 text-[14px]">
                Ready for pickup! Show reference <strong>{{ $order->reference }}</strong> at {{ $order->branch->name }} and pay there.
            </div>
        @elseif(in_array($order->status, ['rejected', 'cancelled']) && $order->status_reason)
            <div class="mt-4 rounded-lg bg-ruby/10 text-ruby px-4 py-3 text-[14px]">{{ $order->statusLabel() }}: {{ $order->status_reason }}</div>
        @endif

        <ol class="mt-5 space-y-2">
            @foreach($order->events as $event)
                <li class="flex gap-3 text-[13px]">
                    <span class="mt-1.5 h-2 w-2 rounded-full bg-primary shrink-0"></span>
                    <span><strong class="font-medium">{{ \App\Models\Order::LABELS[$event->status] ?? $event->status }}</strong>
                        <span class="text-ink-mute">· {{ $event->created_at->format('d M, H:i') }}</span>
                        @if($event->note)<span class="block text-ink-mute">{{ $event->note }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ol>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-4 py-3 border-b border-hairline">
            <p class="text-[15px] font-medium">Pickup at {{ $order->branch->name }}</p>
            <p class="text-[12px] text-ink-mute">{{ $order->branch->merchant->business_name }} · {{ collect([$order->branch->address, $order->branch->city])->filter()->join(', ') }}
                · <a href="https://www.google.com/maps/dir/?api=1&destination={{ $order->branch->latitude }},{{ $order->branch->longitude }}" target="_blank" rel="noopener" class="text-primary">Directions</a></p>
            @if($order->branch->pickup_instructions)<p class="text-[12px] text-ink-secondary mt-1">{{ $order->branch->pickup_instructions }}</p>@endif
        </div>
        <div class="divide-y divide-hairline">
            @foreach($order->items as $item)
                <div class="px-4 py-2.5 flex justify-between gap-3 text-[14px]">
                    <span>{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} × {{ $item->name }}</span>
                    <span class="tnum">TZS {{ number_format($item->line_total) }}</span>
                </div>
            @endforeach
            <div class="px-4 py-3 flex justify-between font-medium">
                <span>Total (pay at pickup)</span><span class="tnum">TZS {{ number_format($order->total) }}</span>
            </div>
        </div>
    </x-ui.card>

    @if($order->canBeCancelledByCustomer())
        <x-ui.card>
            @if($confirmingCancel)
                <form wire:submit="cancel" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <x-ui.input wire:model="reason" label="Reason (optional)" id="cancel_reason" :error="$errors->first('reason')" />
                    </div>
                    <x-ui.button type="submit" variant="danger" target="cancel">Cancel order</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="$set('confirmingCancel', false)">Keep order</x-ui.button>
                </form>
            @else
                <button type="button" wire:click="$set('confirmingCancel', true)" class="text-[13px] text-ruby hover:opacity-80">Cancel this order</button>
                <span class="text-[12px] text-ink-mute ml-2">You can cancel until the store starts preparing it.</span>
            @endif
        </x-ui.card>
    @endif
</div>
