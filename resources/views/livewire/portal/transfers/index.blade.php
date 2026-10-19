<div class="space-y-4">
    <p class="text-[13px] text-ink-mute max-w-3xl">Move stock between your stores. Stock leaves the sending store as soon as you send, and arrives when staff at the other store press <strong>Receive</strong>. You can cancel before it is received and the stock goes back.</p>

    @if($canSend)
        <div>
            <x-ui.button wire:click="$toggle('showForm')">
                {{ $showForm ? 'Cancel' : '+ New transfer' }}
            </x-ui.button>
        </div>
    @endif

    @if($showForm && $canSend)
        <x-ui.card>
            <form wire:submit="send" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <x-ui.select wire:model.live="from_branch_id" label="Send from" id="tr_from" :error="$errors->first('from_branch_id')">
                        <option value="">Choose store…</option>
                        @foreach($fromStores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select wire:model="to_branch_id" label="Send to" id="tr_to" :error="$errors->first('to_branch_id')">
                        <option value="">Choose store…</option>
                        @foreach($toStores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="notes" label="Note (optional)" id="tr_notes" placeholder="e.g. Driver Hamisi, Toyota T 123" />
                </div>

                @if($from_branch_id !== '')
                    <div class="relative max-w-md">
                        <x-ui.input wire:model.live.debounce.250ms="productSearch" label="Add product" id="tr_search" placeholder="Type a product name…" autocomplete="off" />
                        @if($matches->isNotEmpty())
                            <div class="absolute z-10 mt-1 w-full bg-canvas border border-hairline rounded-md shadow-lg divide-y divide-hairline">
                                @foreach($matches as $match)
                                    <button type="button" wire:click="addLine({{ $match->id }})" class="w-full text-left px-3 py-2 text-[13px] hover:bg-canvas-soft flex justify-between gap-3">
                                        <span class="text-ink">{{ $match->name }}</span>
                                        <span class="text-ink-mute tnum">{{ rtrim(rtrim(number_format($match->availableQuantity(), 3, '.', ''), '0'), '.') }} {{ $match->unit }} available</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if($lines)
                    <table class="w-full text-[13px]">
                        <thead class="text-left text-[11px] uppercase tracking-wide text-ink-mute">
                            <tr><th class="py-2 font-normal">Product</th><th class="py-2 font-normal">Available</th><th class="py-2 font-normal w-40">Quantity to send</th><th></th></tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach($lines as $i => $line)
                                <tr wire:key="line-{{ $line['inventory_item_id'] }}">
                                    <td class="py-2 text-ink font-medium">{{ $line['name'] }}</td>
                                    <td class="py-2 text-ink-mute tnum">{{ rtrim(rtrim(number_format($line['available'], 3, '.', ''), '0'), '.') }} {{ $line['unit'] }}</td>
                                    <td class="py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.quantity" class="w-32 rounded-sm border border-hairline-input px-2 py-1 tnum"></td>
                                    <td class="py-2 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="text-[12px] text-ruby">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @error('lines') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                @error('lines.*.quantity') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                @error('quantity') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

                <x-ui.button type="submit" target="send">Send transfer</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Number</th>
                    <th class="px-5 py-3 font-normal">Sent</th>
                    <th class="px-5 py-3 font-normal">From</th>
                    <th class="px-5 py-3 font-normal">To</th>
                    <th class="px-5 py-3 font-normal">Products</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($transfers as $transfer)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">{{ $transfer->number }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $transfer->sent_at?->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $transfer->fromBranch?->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $transfer->toBranch?->name }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $transfer->items_count }}</td>
                        <td class="px-5 py-3">
                            @if($transfer->isSent())
                                <x-ui.badge tone="warning">{{ in_array($transfer->to_branch_id, $storeIds, true) ? 'Waiting for you' : 'On the way' }}</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$transfer->status === 'received' ? 'success' : 'danger'">{{ $transfer->status }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('portal.transfers.show', $transfer) }}" class="text-primary hover:text-primary-deep">View &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $transfers->links() }}</div>
</div>
