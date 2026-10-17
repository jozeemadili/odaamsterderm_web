<div class="max-w-3xl space-y-5">
    <h1 class="text-[24px] font-light tracking-tight">Your cart</h1>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @error('cart') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror

    @forelse($groups as $group)
        <x-ui.card padding="p-0">
            <div class="px-4 py-3 border-b border-hairline flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[15px] font-medium">Pickup at {{ $group['store']->name }}</p>
                    <p class="text-[12px] text-ink-mute">{{ $group['store']->merchant->business_name }} · {{ collect([$group['store']->address, $group['store']->city])->filter()->join(', ') }}</p>
                </div>
                <p class="tnum font-medium">TZS {{ number_format($group['total']) }}</p>
            </div>
            <div class="divide-y divide-hairline">
                @foreach($group['lines'] as $line)
                    <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3" wire:key="line-{{ $line['offer']->id }}">
                        <div>
                            <p class="text-[14px] font-medium">{{ $line['offer']->product?->displayName() ?? $line['offer']->name }}</p>
                            <p class="text-[12px] text-ink-mute">TZS {{ number_format($line['offer']->unit_price) }} per {{ $line['offer']->product?->unit ?? $line['offer']->unit }}</p>
                            @if($line['problem'])<p class="text-[12px] text-ruby">{{ $line['problem'] }}</p>@endif
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="number" min="0" step="1" value="{{ rtrim(rtrim((string) $line['cart_item']->quantity, '0'), '.') }}"
                                wire:change="setQuantity({{ $line['offer']->id }}, $event.target.value)" aria-label="Quantity"
                                class="w-20 rounded-sm border border-hairline-input px-2 py-1 text-[14px] tnum">
                            <p class="w-28 text-right tnum">TZS {{ number_format($line['line_total']) }}</p>
                            <button type="button" wire:click="remove({{ $line['offer']->id }})" class="text-[12px] text-ink-mute hover:text-ruby">Remove</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @empty
        <div class="rounded-xl border border-dashed border-hairline bg-canvas p-10 text-center text-ink-mute">
            Your cart is empty. <a href="{{ route('market.home') }}" class="text-primary hover:text-primary-deep">Browse products</a>
        </div>
    @endforelse

    @if(count($groups))
        <x-ui.card>
            <form wire:submit="checkout" class="space-y-4">
                <div class="flex items-baseline justify-between">
                    <p class="text-[15px]">Total to pay at pickup</p>
                    <p class="text-[22px] font-medium tnum">TZS {{ number_format($total) }}</p>
                </div>
                @if(count($groups) > 1)
                    <p class="text-[13px] text-ink-mute">Your items come from {{ count($groups) }} stores, so you'll get {{ count($groups) }} orders — collect each at its store.</p>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-ui.input wire:model="phone" label="Phone for the store to reach you" id="cart_phone" :error="$errors->first('phone')" />
                    <div class="sm:col-span-2">
                        <x-ui.input wire:model="note" label="Note to the store (optional)" id="cart_note" placeholder="e.g. I'll collect tomorrow morning" />
                    </div>
                </div>
                <x-ui.button type="submit" target="checkout" class="w-full" :disabled="$hasProblems">Place {{ count($groups) > 1 ? count($groups).' orders' : 'order' }} for pickup</x-ui.button>
                @if($hasProblems)
                    <p class="text-[12px] text-ruby">Fix the highlighted items before placing the order.</p>
                @endif
            </form>
        </x-ui.card>
    @endif
</div>
