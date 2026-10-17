<div class="space-y-4" wire:poll.30s>
    <div class="flex flex-wrap gap-2">
        @foreach(\App\Livewire\Portal\Orders\Index::TABS as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="text-[12px] px-3 py-1 rounded-pill border {{ $tab === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">
                {{ $label }}@isset($counts[$key]) ({{ $counts[$key] }})@endisset
            </button>
        @endforeach
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[720px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Order</th>
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Pickup store</th>
                    <th class="px-5 py-3 font-normal">Items</th>
                    <th class="px-5 py-3 font-normal">Total</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($orders as $order)
                    <tr class="hover:bg-canvas-soft/60 cursor-pointer" onclick="window.location='{{ route('portal.orders.show', $order) }}'">
                        <td class="px-5 py-3">
                            <a href="{{ route('portal.orders.show', $order) }}" class="font-medium text-ink hover:text-primary-deep tnum">{{ $order->reference }}</a>
                            <p class="text-[11px] text-ink-mute">{{ $order->created_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $order->customer_name }}<br><span class="text-[11px] text-ink-mute">{{ $order->customer_phone }}</span></td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $order->branch->name }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $order->items_count }}</td>
                        <td class="px-5 py-3 tnum">TZS {{ number_format($order->total) }}</td>
                        <td class="px-5 py-3">@include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">No orders here.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $orders->links() }}
</div>
