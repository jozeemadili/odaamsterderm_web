<div class="max-w-3xl space-y-5">
    <h1 class="text-[24px] font-light tracking-tight">My orders</h1>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @if($notifications->isNotEmpty())
        <x-ui.card padding="p-0">
            <div class="px-4 py-3 border-b border-hairline"><h2 class="text-[15px]">Updates</h2></div>
            <div class="divide-y divide-hairline">
                @foreach($notifications as $n)
                    <a href="{{ isset($n->data['order_id']) ? route('market.order', $n->data['order_id']) : '#' }}" class="block px-4 py-2.5 hover:bg-canvas-soft {{ $n->read_at ? '' : 'bg-primary-subtle/20' }}">
                        <p class="text-[13px] font-medium">{{ $n->data['title'] ?? '' }}</p>
                        <p class="text-[12px] text-ink-mute">{{ $n->data['message'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</p>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="divide-y divide-hairline">
            @forelse($orders as $order)
                <a href="{{ route('market.order', $order) }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-canvas-soft">
                    <div>
                        <p class="text-[14px] font-medium">{{ $order->reference }}</p>
                        <p class="text-[12px] text-ink-mute">{{ $order->branch->name }} · {{ $order->created_at->format('d M Y H:i') }} · {{ $order->items_count }} {{ \Illuminate\Support\Str::plural('item', $order->items_count) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="tnum font-medium">TZS {{ number_format($order->total) }}</p>
                        @include('livewire.shared.order-status-badge', ['status' => $order->status, 'label' => $order->statusLabel()])
                    </div>
                </a>
            @empty
                <p class="px-4 py-10 text-center text-ink-mute text-[14px]">No orders yet. <a href="{{ route('market.home') }}" class="text-primary">Find products near you</a>.</p>
            @endforelse
        </div>
    </x-ui.card>

    {{ $orders->links() }}
</div>
