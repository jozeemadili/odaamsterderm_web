<div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-4 items-start">
    <div class="space-y-4">
        @if($lastReceiptTotal)
            <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px] flex items-center justify-between">
                <span>Sale completed — <span class="tnum font-medium">TZS {{ $lastReceiptTotal }}</span> collected.</span>
                <button wire:click="$set('lastReceiptTotal', null)" class="text-primary-deep/60 hover:text-primary-deep">&times;</button>
            </div>
        @endif

        {{-- Customer --}}
        <x-ui.card padding="p-4">
            <div class="flex items-center justify-between">
                <div class="text-[13px]">
                    <span class="text-ink-mute">Customer:</span>
                    <span class="text-ink font-medium ml-1">{{ $customerLabel ?? 'Walk-in customer' }}</span>
                </div>
                <button wire:click="$toggle('showCustomerPicker')" class="text-[13px] text-primary hover:text-primary-deep">
                    {{ $showCustomerPicker ? 'Close' : 'Change' }}
                </button>
            </div>

            @if($showCustomerPicker)
                <div class="mt-3 pt-3 border-t border-hairline space-y-3">
                    <div class="flex gap-2">
                        <button wire:click="useWalkIn" class="text-[12px] px-3 py-1 rounded-pill border border-hairline hover:border-primary/40">Walk-in</button>
                        <button wire:click="$toggle('showNewCustomerForm')" class="text-[12px] px-3 py-1 rounded-pill border border-hairline hover:border-primary/40">+ New customer</button>
                    </div>

                    @if($showNewCustomerForm)
                        <div class="flex flex-wrap gap-2 items-end">
                            <input type="text" wire:model="newCustomerName" placeholder="Full name" class="rounded-sm border border-hairline-input text-[13px] px-2.5 py-1.5">
                            <input type="text" wire:model="newCustomerPhone" placeholder="Phone" class="rounded-sm border border-hairline-input text-[13px] px-2.5 py-1.5">
                            <x-ui.button size="sm" wire:click="createCustomer" target="createCustomer">Add &amp; select</x-ui.button>
                        </div>
                        @error('newCustomerName') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                    @else
                        <input type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="Search customers by name or phone" class="w-full rounded-sm border border-hairline-input text-[13px] px-2.5 py-1.5">
                        <div class="space-y-1 max-h-40 overflow-y-auto">
                            @foreach($customers as $c)
                                <button wire:click="selectCustomer({{ $c->id }})" class="w-full text-left text-[13px] px-2.5 py-1.5 rounded hover:bg-canvas-soft">
                                    {{ $c->name }} <span class="text-ink-mute">{{ $c->phone }}</span>
                                </button>
                            @endforeach
                            @if($customerSearch !== '' && $customers->isEmpty())
                                <p class="text-[12px] text-ink-mute px-2.5">No matches.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.card>

        <input
            type="text"
            wire:model.live.debounce.250ms="search"
            placeholder="Search by name, SKU, or scan barcode"
            class="w-full rounded-sm border border-hairline-input bg-canvas text-[14px] px-3.5 py-2.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
        >

        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
            @forelse($items as $item)
                @php
                    $inCart = $cart[$item->id]['quantity'] ?? 0;
                    $remaining = (float) $item->quantity_on_hand - $inCart;
                    $outOfStock = $remaining <= 0;
                @endphp
                <button
                    type="button"
                    wire:click="addToCart({{ $item->id }})"
                    wire:loading.attr="disabled"
                    wire:target="addToCart({{ $item->id }})"
                    @disabled($outOfStock)
                    class="text-left bg-canvas border border-hairline rounded-lg p-3.5 hover:border-primary/50 hover:shadow-[0_1px_3px_rgba(0,55,112,0.08)] transition disabled:opacity-40 disabled:cursor-not-allowed relative"
                >
                    @if($inCart > 0)
                        <span class="absolute -top-2 -right-2 h-5 min-w-5 px-1 rounded-full bg-primary text-ink text-[11px] font-medium flex items-center justify-center tnum">{{ rtrim(rtrim(number_format($inCart, 2, '.', ''), '0'), '.') }}</span>
                    @endif
                    @if($item->image())
                        <img src="{{ $item->image()->getUrl() }}" alt="" class="h-16 w-full object-cover rounded mb-2 border border-hairline">
                    @endif
                    <p class="text-[13px] text-ink font-medium leading-snug mb-1">{{ $item->name }}</p>
                    @if($item->branch)
                        <p class="text-[10px] text-ink-mute mb-1 uppercase tracking-wide">{{ $item->branch->name }}</p>
                    @endif
                    <p class="text-[15px] text-primary-deep tnum font-medium">TZS {{ number_format($item->unit_price, 0) }}</p>
                    <p class="text-[11px] mt-1 {{ $outOfStock ? 'text-ruby' : 'text-ink-mute' }}">
                        {{ $outOfStock ? 'Out of stock' : rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.') . ' ' . $item->unit . ' left' }}
                    </p>
                </button>
            @empty
                <div class="col-span-full text-center py-12 text-ink-mute text-[13px]">
                    No sellable products found. Add items with a price in <a href="{{ route('portal.inventory.index') }}" class="text-primary hover:text-primary-deep">Inventory</a>.
                </div>
            @endforelse
        </div>
    </div>

    <div class="lg:sticky lg:top-4">
        <x-ui.card padding="p-0">
            <div class="px-5 py-4 border-b border-hairline flex items-center justify-between">
                <h2 class="text-[15px] text-ink-secondary">Current sale</h2>
                @if(count($cart) > 0)
                    <button wire:click="clearCart" class="text-[12px] text-ink-mute hover:text-ruby">Clear</button>
                @endif
            </div>

            <div class="max-h-[38vh] overflow-y-auto divide-y divide-hairline">
                @forelse($cart as $itemId => $line)
                    @php
                        $lineHasDiscount = ! empty($line['discount_type']) && ! empty($line['discount_value']);
                        $discountedUnitPrice = $this->discountedUnitPrice($line);
                        $lineTotal = $discountedUnitPrice * $line['quantity'];
                    @endphp
                    <div class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] text-ink font-medium truncate">{{ $line['name'] }}</p>
                                <p class="text-[12px] text-ink-mute tnum">
                                    @if($lineHasDiscount)
                                        <span class="line-through">TZS {{ number_format($line['unit_price'], 0) }}</span>
                                        <span class="text-primary-deep font-medium">TZS {{ number_format($discountedUnitPrice, 0) }}</span> each
                                    @else
                                        TZS {{ number_format($line['unit_price'], 0) }} each
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button wire:click="decrementQty({{ $itemId }})" class="h-6 w-6 rounded-full border border-hairline text-ink-secondary hover:border-primary/50 flex items-center justify-center text-[13px]">&minus;</button>
                                <span class="w-6 text-center text-[13px] tnum">{{ rtrim(rtrim(number_format($line['quantity'], 2, '.', ''), '0'), '.') }}</span>
                                <button wire:click="incrementQty({{ $itemId }})" class="h-6 w-6 rounded-full border border-hairline text-ink-secondary hover:border-primary/50 flex items-center justify-center text-[13px]">&plus;</button>
                            </div>
                            <p class="w-16 text-right text-[13px] text-ink tnum">{{ number_format($lineTotal, 0) }}</p>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <button wire:click="startLineDiscount({{ $itemId }})" class="text-[11px] {{ $lineHasDiscount ? 'text-primary-deep font-medium' : 'text-ink-mute' }} hover:text-primary-deep">
                                {{ $lineHasDiscount ? 'Discount: '.($line['discount_type'] === 'percent' ? $line['discount_value'].'% per unit' : 'TZS '.number_format($line['discount_value'], 0).' per unit') : '+ discount' }}
                            </button>
                            <button wire:click="removeFromCart({{ $itemId }})" class="text-[11px] text-ink-mute hover:text-ruby">remove</button>
                        </div>

                        @if($discountingItemId === $itemId)
                            <div class="mt-2 p-2.5 bg-canvas-soft rounded-md flex items-end gap-2">
                                <select wire:model="lineDiscountType" class="text-[12px] rounded-sm border border-hairline-input px-1.5 py-1">
                                    <option value="percent">%</option>
                                    <option value="fixed">TZS</option>
                                </select>
                                <input type="number" step="0.01" wire:model="lineDiscountValue" class="w-20 text-[12px] rounded-sm border border-hairline-input px-1.5 py-1">
                                <x-ui.button size="sm" wire:click="saveLineDiscount" target="saveLineDiscount">Apply</x-ui.button>
                                @if($lineHasDiscount)
                                    <button wire:click="clearLineDiscount({{ $itemId }})" class="text-[11px] text-ruby">clear</button>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-ink-mute text-[13px]">
                        Tap a product to add it to the sale.
                    </div>
                @endforelse
            </div>

            @if(count($cart) > 0)
                <div class="px-5 py-3 border-t border-hairline">
                    <div class="flex items-center justify-between text-[12px] text-ink-mute mb-2">
                        <span>Overall discount</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <select wire:model="overallDiscountType" class="text-[12px] rounded-sm border border-hairline-input px-1.5 py-1">
                            <option value="percent">%</option>
                            <option value="fixed">TZS</option>
                        </select>
                        <input type="number" step="0.01" wire:model.live.debounce.400ms="overallDiscountValue" placeholder="0" class="flex-1 text-[12px] rounded-sm border border-hairline-input px-2 py-1">
                    </div>
                </div>
            @endif

            <div class="px-5 py-4 border-t border-hairline space-y-2">
                <div class="flex items-center justify-between text-[12px] text-ink-mute">
                    <span>Subtotal</span>
                    <span class="tnum">TZS {{ number_format($totals['subtotal'], 0) }}</span>
                </div>
                @if($totals['overallDiscount'] > 0)
                    <div class="flex items-center justify-between text-[12px] text-ruby">
                        <span>Overall discount</span>
                        <span class="tnum">&minus;TZS {{ number_format($totals['overallDiscount'], 0) }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between pt-1">
                    <span class="text-[13px] text-ink-mute">Total</span>
                    <span class="text-[22px] font-light tracking-tight text-ink tnum">TZS {{ number_format($totals['total'], 0) }}</span>
                </div>

                @if(count($cart) > 0)
                    <div class="pt-2 space-y-2">
                        <div>
                            <label class="block text-[11px] text-ink-mute mb-1">Payment method</label>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($paymentMethods as $pm)
                                    <button
                                        type="button"
                                        wire:click="$set('paymentMethodId', {{ $pm->id }})"
                                        class="inline-flex items-center gap-1.5 text-[12px] px-2.5 py-1 rounded-pill border {{ $paymentMethodId === $pm->id ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}"
                                    >
                                        <x-payment-method-badge :method="$pm" size="h-4 w-4 text-[8px]" />
                                        {{ $pm->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-ink-mute mb-1">Amount received</label>
                                <input type="number" step="0.01" wire:model.live.debounce.300ms="amountTendered" placeholder="{{ number_format($totals['total'], 0) }}" class="w-full text-[13px] rounded-sm border border-hairline-input px-2.5 py-1.5 tnum">
                            </div>
                            <div>
                                <label class="block text-[11px] text-ink-mute mb-1">Change</label>
                                <div class="text-[13px] rounded-sm border border-hairline bg-canvas-soft px-2.5 py-1.5 tnum text-ink font-medium">
                                    TZS {{ number_format($this->changeDue(), 0) }}
                                </div>
                            </div>
                        </div>
                        @if($this->amountShort() > 0)
                            <p class="text-[11px] text-ruby">TZS {{ number_format($this->amountShort(), 0) }} short of the total.</p>
                        @endif
                    </div>
                @endif

                @if($this->effectiveDiscountPercent() > 0)
                    <p class="text-[11px] text-ink-mute">Effective discount: {{ $this->effectiveDiscountPercent() }}% (your limit: {{ $this->myDiscountLimit() }}%)</p>
                @endif

                @if($checkoutError)
                    <p class="text-ruby text-[12px]">{{ $checkoutError }}</p>
                @endif

                @if($discountApprovedByName)
                    <p class="text-[11px] text-success">Discount approved by {{ $discountApprovedByName }}</p>
                @endif

                @if($showOverridePanel)
                    <div class="p-3 bg-canvas-cream rounded-md space-y-2">
                        <p class="text-[12px] text-lemon">Ask a supervisor or manager to authorize this discount:</p>
                        <input type="email" wire:model="overrideEmail" placeholder="Approver email" class="w-full text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                        <input type="password" wire:model="overridePassword" placeholder="Approver password" class="w-full text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                        @error('overridePassword') <p class="text-ruby text-[11px]">{{ $message }}</p> @enderror
                        @error('overrideEmail') <p class="text-ruby text-[11px]">{{ $message }}</p> @enderror
                        <x-ui.button size="sm" wire:click="authorizeOverride" target="authorizeOverride" class="w-full">Authorize</x-ui.button>
                    </div>
                @endif

                <x-ui.button
                    wire:click="checkout"
                    target="checkout"
                    class="w-full"
                    :disabled="count($cart) === 0"
                >
                    Complete sale
                </x-ui.button>
            </div>
        </x-ui.card>
    </div>
</div>
