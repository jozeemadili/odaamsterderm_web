<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('portal.transfers.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; All transfers</a>
        <a href="{{ \App\Http\Controllers\TransferNoteController::url($transfer) }}" target="_blank" class="text-[13px] text-primary hover:text-primary-deep ml-auto">Print transfer note</a>
    </div>

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-[20px] text-ink font-medium">{{ $transfer->number }}</h2>
                <p class="text-[14px] text-ink-secondary mt-1"><strong>{{ $transfer->fromBranch?->name }}</strong> &rarr; <strong>{{ $transfer->toBranch?->name }}</strong></p>
                <p class="text-[12.5px] text-ink-mute mt-1">Sent {{ $transfer->sent_at?->format('d M Y H:i') }} by {{ $transfer->sender?->name ?? '—' }}</p>
                @if($transfer->received_at)
                    <p class="text-[12.5px] text-ink-mute">Received {{ $transfer->received_at->format('d M Y H:i') }} by {{ $transfer->receiver?->name ?? '—' }}</p>
                @endif
                @if($transfer->cancelled_at)
                    <p class="text-[12.5px] text-ink-mute">Cancelled {{ $transfer->cancelled_at->format('d M Y H:i') }}</p>
                @endif
                @if($transfer->notes)
                    <p class="text-[13px] text-ink-secondary mt-2">{{ $transfer->notes }}</p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-2">
                @if($transfer->isSent())
                    <x-ui.badge tone="warning">On the way</x-ui.badge>
                @else
                    <x-ui.badge :tone="$transfer->status === 'received' ? 'success' : 'danger'">{{ $transfer->status }}</x-ui.badge>
                @endif
                <div class="flex gap-2">
                    @if($transfer->canReceive())
                        <x-ui.button wire:click="receive" target="receive" wire:confirm="Confirm the stock has arrived in {{ $transfer->toBranch?->name }}?">Receive stock</x-ui.button>
                    @endif
                    @if($transfer->canCancel())
                        <x-ui.button variant="ghost" wire:click="cancel" target="cancel" wire:confirm="Cancel this transfer and return the stock to {{ $transfer->fromBranch?->name }}?">Cancel transfer</x-ui.button>
                    @endif
                </div>
            </div>
        </div>
        @error('transfer') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Quantity</th>
                    <th class="px-5 py-3 font-normal">In {{ $transfer->toBranch?->name }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @foreach($lines as $line)
                    <tr>
                        <td class="px-5 py-3 text-ink font-medium">{{ $line->item_name }}</td>
                        <td class="px-5 py-3 tnum text-ink">{{ rtrim(rtrim($line->quantity, '0'), '.') }} {{ $line->unit }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">
                            @if($line->toItem)
                                {{ rtrim(rtrim(number_format($line->toItem->quantity_on_hand, 3, '.', ''), '0'), '.') }} {{ $line->toItem->unit }} now
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</div>
