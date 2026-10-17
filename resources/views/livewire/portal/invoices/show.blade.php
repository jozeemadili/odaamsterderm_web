<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-[15px] text-ink-secondary">{{ $invoice->number }}</h2>
                <x-ui.badge :tone="match($invoice->status) {
                    'paid' => 'success',
                    'partially_paid' => 'warning',
                    'cancelled' => 'danger',
                    'invoiced' => 'primary',
                    default => 'neutral',
                }">{{ $invoice->statusLabel() }}</x-ui.badge>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-[12px] text-ink-secondary">
                    <input type="checkbox" wire:model="includeImages" class="rounded border-hairline-input">
                    Include product images
                </label>
                <x-ui.button variant="secondary" size="sm" wire:click="downloadPdf" target="downloadPdf">
                    Download {{ $invoice->isDraft() ? 'Proforma' : 'Invoice' }} PDF
                </x-ui.button>
                @if($invoice->isDraft())
                    @can('approve-stock-receipts')
                        <x-ui.button variant="danger" size="sm" wire:click="cancel" target="cancel" wire:confirm="Cancel this proforma?">Cancel</x-ui.button>
                        <x-ui.button variant="primary" size="sm" wire:click="approve" target="approve" wire:confirm="Approve this invoice? Inventory will be reduced and quantities/discounts can no longer be edited.">Approve &amp; send</x-ui.button>
                    @endcan
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-5 text-[13px]">
            <div><p class="text-ink-mute mb-1">Customer</p><p class="text-ink font-medium">{{ $invoice->customer->name }}</p></div>
            <div><p class="text-ink-mute mb-1">Issue date</p><p class="text-ink font-medium">{{ $invoice->issue_date->format('d M Y') }}</p></div>
            <div><p class="text-ink-mute mb-1">Due date</p><p class="text-ink font-medium">{{ $invoice->due_date?->format('d M Y') ?: '—' }}</p></div>
            <div><p class="text-ink-mute mb-1">Total</p><p class="text-ink font-medium tnum">TZS {{ number_format($invoice->total, 0) }}</p></div>
            <div><p class="text-ink-mute mb-1">Balance due</p><p class="font-medium tnum {{ $invoice->balanceDue() > 0 ? 'text-ruby' : 'text-success' }}">TZS {{ number_format($invoice->balanceDue(), 0) }}</p></div>
        </div>
    </x-ui.card>

    @if($invoice->isDraft())
        <x-ui.card>
            <h2 class="text-[15px] text-ink-secondary mb-4">Add product</h2>
            <form wire:submit="addItem" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div class="md:col-span-2 relative">
                    <label class="block text-[13px] text-ink-mute mb-1.5">Product</label>
                    @if($selectedItemLabel)
                        <div class="flex items-center justify-between rounded-sm border border-hairline-input bg-canvas-soft text-[14px] px-3 py-2">
                            <span class="text-ink">{{ $selectedItemLabel }}</span>
                            <button type="button" wire:click="clearSelectedProduct" class="text-ink-mute hover:text-ruby text-[12px]">&times;</button>
                        </div>
                    @else
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="productSearch"
                            placeholder="Search product by name…"
                            autocomplete="off"
                            class="w-full rounded-sm border border-hairline-input bg-canvas text-ink text-[15px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition"
                        >
                        @if(count($productMatches) > 0)
                            <div class="absolute z-10 mt-1 w-full max-w-full sm:min-w-[320px] border border-hairline rounded-md bg-canvas shadow-lg divide-y divide-hairline overflow-hidden">
                                @foreach($productMatches as $match)
                                    <button
                                        type="button"
                                        wire:click="selectProduct({{ $match['id'] }})"
                                        class="w-full flex items-center justify-between px-3 py-2 text-left text-[13px] hover:bg-canvas-soft"
                                    >
                                        <span class="text-ink font-medium">{{ $match['name'] }}</span>
                                        <span class="text-ink-mute text-[11px] text-right tnum">
                                            {{ rtrim(rtrim(number_format($match['quantity_on_hand'], 2, '.', ''), '0'), '.') }} {{ $match['unit'] }} left
                                            @if($match['unit_price'])<br>TZS {{ number_format($match['unit_price'], 0) }}@endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    @error('selectedItemId') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                </div>
                <x-ui.input type="number" step="0.01" wire:model="quantity" label="Qty" id="inv_qty" />
                <x-ui.input type="number" step="0.01" wire:model="unit_price" label="Unit price" id="inv_price" />
                <div class="flex items-end gap-1.5">
                    <select wire:model="lineDiscountType" class="text-[13px] rounded-sm border border-hairline-input px-2 py-2">
                        <option value="percent">%</option>
                        <option value="fixed">TZS</option>
                    </select>
                    <input type="number" step="0.01" wire:model="lineDiscountValue" placeholder="Discount" class="w-full text-[13px] rounded-sm border border-hairline-input px-2 py-2">
                </div>
                <div class="md:col-span-5">
                    <x-ui.button type="submit" target="addItem">Add to invoice</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit price</th>
                    <th class="px-5 py-3 font-normal">Discount</th>
                    <th class="px-5 py-3 font-normal text-right">Subtotal</th>
                    @if($invoice->isDraft())
                        <th class="px-5 py-3"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($lines as $line)
                    @if($editingItemId === $line->id)
                        <tr class="bg-canvas-soft/60">
                            <td class="px-5 py-3 text-ink font-medium">{{ $line->item_name }}</td>
                            <td class="px-5 py-2" colspan="4">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div>
                                        <label class="block text-[11px] text-ink-mute mb-1">Qty</label>
                                        <input type="number" step="0.01" wire:model="edit_quantity" class="w-20 text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-ink-mute mb-1">Unit price</label>
                                        <input type="number" step="0.01" wire:model="edit_unit_price" class="w-28 text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-ink-mute mb-1">Discount</label>
                                        <div class="flex gap-1">
                                            <select wire:model="edit_discount_type" class="text-[12px] rounded-sm border border-hairline-input px-1.5 py-1.5">
                                                <option value="percent">%</option>
                                                <option value="fixed">TZS</option>
                                            </select>
                                            <input type="number" step="0.01" wire:model="edit_discount_value" class="w-20 text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                                        </div>
                                    </div>
                                    <x-ui.button size="sm" wire:click="saveEditItem" target="saveEditItem">Save</x-ui.button>
                                    <button type="button" wire:click="cancelEditItem" class="text-[12px] text-ink-mute hover:text-ink">Cancel</button>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td class="px-5 py-3 text-ink font-medium">{{ $line->item_name }}</td>
                            <td class="px-5 py-3 tnum text-ink-secondary">{{ rtrim(rtrim(number_format($line->quantity, 2, '.', ''), '0'), '.') }}</td>
                            <td class="px-5 py-3 tnum text-ink-secondary">{{ number_format($line->unit_price, 0) }}</td>
                            <td class="px-5 py-3 tnum text-ink-mute">{{ $line->discount_amount > 0 ? '−'.number_format($line->discount_amount, 0) : '—' }}</td>
                            <td class="px-5 py-3 tnum text-ink text-right">{{ number_format($line->subtotal, 0) }}</td>
                            @if($invoice->isDraft())
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <button wire:click="startEditItem({{ $line->id }})" class="text-primary text-[12px] hover:underline">Edit</button>
                                    <button wire:click="removeItem({{ $line->id }})" class="text-ruby text-[12px] hover:underline ml-2">Remove</button>
                                </td>
                            @endif
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">No products added yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if($invoice->isDraft() && $lines->isNotEmpty())
            <div class="px-5 py-4 border-t border-hairline flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-end gap-2">
                    <div>
                        <label class="block text-[11px] text-ink-mute mb-1">Overall discount</label>
                        <div class="flex gap-1.5">
                            <select wire:model="overallDiscountType" class="text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                                <option value="percent">%</option>
                                <option value="fixed">TZS</option>
                            </select>
                            <input type="number" step="0.01" wire:model.live.debounce.400ms="overallDiscountValue" placeholder="0" class="w-28 text-[12px] rounded-sm border border-hairline-input px-2 py-1.5">
                        </div>
                    </div>
                </div>
                <div class="text-right text-[13px] space-y-1">
                    <div class="text-ink-mute">Subtotal: <span class="tnum text-ink">TZS {{ number_format($invoice->subtotal, 0) }}</span></div>
                    @if($invoice->discount_amount > 0)
                        <div class="text-ruby">Discount: <span class="tnum">−TZS {{ number_format($invoice->discount_amount, 0) }}</span></div>
                    @endif
                    <div class="text-[16px] text-ink">Total: <span class="tnum font-medium">TZS {{ number_format($invoice->total, 0) }}</span></div>
                </div>
            </div>
        @endif
    </x-ui.card>

    @if(! $invoice->isDraft() && ! $invoice->isCancelled())
        <x-ui.card padding="p-0">
            <div class="px-5 py-4 border-b border-hairline">
                <h2 class="text-[15px] text-ink-secondary">Payments</h2>
            </div>

            @if(! $invoice->isFullyPaid())
                <div class="px-5 py-4 border-b border-hairline">
                    <form wire:submit="recordPayment" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <x-ui.input type="number" step="0.01" wire:model="paymentAmount" label="Amount received" id="pay_amount" placeholder="Up to {{ number_format($invoice->balanceDue(), 0) }}" />
                        <x-ui.select wire:model="paymentMethodId" label="Method" id="pay_method">
                            <option value="">Select</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input type="date" wire:model="paymentDate" label="Date" id="pay_date" />
                        <x-ui.input wire:model="paymentReference" label="Reference (optional)" id="pay_reference" />
                        <div class="md:col-span-4">
                            <x-ui.button type="submit" target="recordPayment">Record payment</x-ui.button>
                        </div>
                    </form>
                    @error('paymentAmount') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
                    @error('paymentDate') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
                </div>
            @endif

            <table class="w-full text-[13px]">
                <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                    <tr>
                        <th class="px-5 py-3 font-normal">Date</th>
                        <th class="px-5 py-3 font-normal">Method</th>
                        <th class="px-5 py-3 font-normal">Reference</th>
                        <th class="px-5 py-3 font-normal">Recorded by</th>
                        <th class="px-5 py-3 font-normal text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-5 py-2.5 text-ink-secondary">{{ $payment->payment_date->format('d M Y') }}</td>
                            <td class="px-5 py-2.5 text-ink-secondary">
                                @if($payment->paymentMethod)
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-payment-method-badge :method="$payment->paymentMethod" />
                                        {{ $payment->paymentMethod->name }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-ink-mute">{{ $payment->reference ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-ink-mute">{{ $payment->recordedBy?->name }}</td>
                            <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($payment->amount, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-ink-mute">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    @endif
</div>
