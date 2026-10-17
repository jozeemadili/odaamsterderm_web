<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        @foreach($statuses as $key => $label)
            <button wire:click="$set('status', '{{ $key }}')" class="text-[12px] px-3 py-1 rounded-pill border {{ $status === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="flex flex-wrap gap-3">
        <div class="w-64"><x-ui.input wire:model.live.debounce.300ms="search" placeholder="Reference or customer…" id="admin_order_search" /></div>
        <div class="w-64">
            <x-ui.select wire:model.live="seller" id="admin_order_seller">
                <option value="">All sellers</option>
                @foreach($sellers as $s)<option value="{{ $s->id }}">{{ $s->business_name }}</option>@endforeach
            </x-ui.select>
        </div>
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[860px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Order</th>
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Seller · store</th>
                    <th class="px-5 py-3 font-normal">Total</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal">Age</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($orders as $order)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-primary tnum">{{ $order->reference }}</a></td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $order->customer_name }}<br><span class="text-[11px] text-ink-mute">{{ $order->customer_phone }}</span></td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $order->merchant->business_name }}<br><span class="text-[11px] text-ink-mute">{{ $order->branch->name }}</span></td>
                        <td class="px-5 py-3 tnum">TZS {{ number_format($order->total) }}</td>
                        <td class="px-5 py-3">@include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])</td>
                        <td class="px-5 py-3 text-ink-mute">{{ $order->created_at->diffForHumans(null, true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">No orders.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $orders->links() }}
</div>
