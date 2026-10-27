<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="flex items-start gap-4">
            <div class="shrink-0 flex gap-3">
                @foreach(['front' => ['Front', $item->image(), 'photo'], 'back' => ['Back', $item->backImage(), 'backPhoto']] as $side => [$label, $media, $field])
                    <div class="w-24 text-center">
                        @if($media)
                            <img src="{{ $media->getUrl() }}" alt="{{ $label }} of {{ $item->name }}" class="h-24 w-24 rounded-lg object-cover border border-hairline">
                        @else
                            <div class="h-24 w-24 rounded-lg bg-canvas-soft border border-dashed border-hairline flex items-center justify-center text-ink-mute text-[11px] px-2">No {{ strtolower($label) }} photo</div>
                        @endif
                        <p class="mt-1 text-[10.5px] uppercase tracking-wide text-ink-mute">{{ $label }}</p>
                        <label class="block cursor-pointer text-[12px] text-primary hover:text-primary-deep">
                            <span wire:loading.remove wire:target="{{ $field }}">{{ $media ? 'Change' : 'Add' }}</span>
                            <span wire:loading wire:target="{{ $field }}">Uploading…</span>
                            <input type="file" wire:model="{{ $field }}" accept="image/*" class="sr-only">
                        </label>
                        @if($media)
                            <button type="button" wire:click="removePhoto('{{ $side }}')" wire:confirm="Remove the {{ strtolower($label) }} photo?" class="text-[11px] text-ink-mute hover:text-ruby">Remove</button>
                        @endif
                        @error($field)<p class="text-[11px] text-ruby mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-[20px] font-light text-ink tracking-tight">{{ $item->name }}</h2>
                    @if($item->isLowStock())
                        <x-ui.badge tone="danger">low stock</x-ui.badge>
                    @endif
                    @if($item->isExpiringSoon())
                        <x-ui.badge tone="warning">expires {{ $item->expiry_date->format('d M Y') }}</x-ui.badge>
                    @elseif($item->isExpired())
                        <x-ui.badge tone="danger">expired {{ $item->expiry_date->format('d M Y') }}</x-ui.badge>
                    @endif
                    @if($item->branch)
                        <x-ui.badge tone="primary">{{ $item->branch->name }}</x-ui.badge>
                    @endif
                </div>
                <p class="text-[13px] text-ink-mute mt-1">
                    {{ $item->category?->name ?? 'Uncategorized' }}
                    @if($item->sku) &middot; SKU {{ $item->sku }} @endif
                    @if($item->barcode) &middot; Barcode {{ $item->barcode }} @endif
                    @if($item->expiry_date && ! $item->isExpiringSoon() && ! $item->isExpired()) &middot; Expires {{ $item->expiry_date->format('d M Y') }} @endif
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-[13px]">
                    <div><p class="text-ink-mute mb-1">On hand</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}</p></div>
                    <div><p class="text-ink-mute mb-1">Reorder level</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->reorder_level, '0'), '.') }}</p></div>
                    <div><p class="text-ink-mute mb-1">Unit cost</p><p class="text-ink font-medium tnum">{{ $item->unit_cost ? number_format($item->unit_cost, 0) : '—' }}</p></div>
                    <div><p class="text-ink-mute mb-1">Selling price</p><p class="text-ink font-medium tnum">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</p></div>
                </div>
            </div>
            <a href="{{ route('portal.inventory.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Back</a>
        </div>

        <div class="mt-6 pt-5 border-t border-hairline">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] text-ink-secondary">Marketplace</h3>
                @if($item->is_marketplace_visible)
                    <x-ui.badge :tone="$item->marketplace_status === 'active' ? 'success' : ($item->marketplace_status === 'out_of_stock' ? 'danger' : 'neutral')">{{ str_replace('_', ' ', $item->marketplace_status) }}</x-ui.badge>
                @else
                    <x-ui.badge tone="neutral">hidden</x-ui.badge>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <form wire:submit="saveCatalogLink" class="space-y-3">
                    @include('livewire.shared.catalog-picker')
                    @if($product_id !== $item->product_id)
                        <x-ui.button type="submit" size="sm" target="saveCatalogLink">Save catalog link</x-ui.button>
                    @endif
                    @if($item->product)
                        <p class="text-[12px] text-ink-mute">{{ $item->product->animalType?->fullName() }} · {{ $item->product->category?->fullName() }} @if($item->product->makerName()) · by {{ $item->product->makerName() }} @endif</p>
                    @endif
                </form>

                <form wire:submit="saveMarketplace" class="space-y-3">
                    <div class="grid grid-cols-3 gap-3 text-[13px]">
                        <div><p class="text-ink-mute mb-1">Actual stock</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }}</p></div>
                        <div><p class="text-ink-mute mb-1">Reserved (orders)</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim($item->reserved_quantity, '0'), '.') }}</p></div>
                        <div><p class="text-ink-mute mb-1">Can expose</p><p class="text-ink font-medium tnum">{{ rtrim(rtrim(number_format($item->availableQuantity(), 2, '.', ''), '0'), '.') }}</p></div>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-40">
                            <x-ui.input type="number" step="0.01" min="0" wire:model="mpQuantity" label="Marketplace quantity" id="mp_quantity" />
                        </div>
                        <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary pb-2">
                            <input type="checkbox" wire:model="mpVisible" class="rounded border-hairline-input text-primary focus:ring-primary"> Visible on marketplace
                        </label>
                        <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary pb-2">
                            <input type="checkbox" wire:model="mpActive" class="rounded border-hairline-input text-primary focus:ring-primary"> Active
                        </label>
                        <x-ui.button type="submit" size="sm" target="saveMarketplace">Save</x-ui.button>
                    </div>
                    @error('mpQuantity') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                    @if($blockers = $item->marketplaceBlockers())
                        <ul class="text-[12px] text-lemon list-disc pl-4 space-y-0.5">
                            @foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                        </ul>
                    @else
                        <p class="text-[12px] text-ink-mute">Customers see the marketplace quantity; you always see actual stock. Offline sales reduce the marketplace quantity automatically when stock runs short.</p>
                    @endif
                </form>
            </div>
        </div>

        @if($item->barcode && $this->barcodeSvg())
            <div class="mt-4 pt-4 border-t border-hairline flex items-center gap-4">
                <div class="bg-white p-2 rounded border border-hairline [&_svg]:h-16">{!! $this->barcodeSvg() !!}</div>
                <p class="text-[12px] text-ink-mute">Print and attach this barcode to the physical product for scanning at checkout.</p>
            </div>
        @endif
    </x-ui.card>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Units sold (all time)" :value="rtrim(rtrim(number_format($unitsSold, 2, '.', ''), '0'), '.')" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Revenue" value="TZS {{ number_format($revenue, 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Discounts given" value="TZS {{ number_format($discountGiven, 0) }}" tone="ruby" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Est. profit" value="TZS {{ number_format($estimatedProfit, 0) }}" :tone="$estimatedProfit >= 0 ? 'primary' : 'ruby'" />
            <p class="text-[11px] text-ink-mute mt-1">{{ $marginPercent }}% margin &middot; based on current unit cost</p>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-2 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Units received (approved receipts)" :value="rtrim(rtrim(number_format($unitsReceived, 2, '.', ''), '0'), '.')" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total spent purchasing" value="TZS {{ number_format($totalPurchaseCost, 0) }}" />
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Purchase history</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Supplier</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit cost</th>
                    <th class="px-5 py-3 font-normal text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($purchases as $p)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $p->stockReceipt->receipt_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $p->stockReceipt->supplier?->name ?? '—' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($p->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ number_format($p->unit_cost, 0) }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($p->subtotal, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-6 text-center text-ink-mute">No approved purchases yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Sales history</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit price</th>
                    <th class="px-5 py-3 font-normal">Discount</th>
                    <th class="px-5 py-3 font-normal text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($sales as $s)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $s->sale->sale_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $s->sale->customer?->name ?? 'Walk-in' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($s->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ number_format($s->unit_price, 0) }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-mute">{{ $s->discount_amount > 0 ? '−'.number_format($s->discount_amount, 0) : '—' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink text-right">{{ number_format($s->subtotal, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-ink-mute">Not sold yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Stock movement log</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Type</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Stock after</th>
                    <th class="px-5 py-3 font-normal">Online after</th>
                    <th class="px-5 py-3 font-normal">Reference</th>
                    <th class="px-5 py-3 font-normal">By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($movements as $m)
                    <tr>
                        <td class="px-5 py-2.5 text-ink-secondary">{{ $m->movement_date->format('d M Y') }}</td>
                        <td class="px-5 py-2.5">
                            <x-ui.badge :tone="$m->type === 'in' ? 'success' : ($m->type === 'out' ? 'danger' : 'neutral')">{{ $m->type }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ rtrim(rtrim($m->quantity, '0'), '.') }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ $m->balance_after !== null ? rtrim(rtrim($m->balance_after, '0'), '.') : '—' }}</td>
                        <td class="px-5 py-2.5 tnum text-ink-secondary">{{ $m->marketplace_quantity_after !== null ? rtrim(rtrim($m->marketplace_quantity_after, '0'), '.') : '—' }}</td>
                        <td class="px-5 py-2.5 text-ink-mute">
                            {{ $m->notes ?: ucfirst(str_replace('_', ' ', (string) $m->reference)) }}
                            @if($m->reason)<span class="block text-[11px]">Reason: {{ $m->reason }}</span>@endif
                        </td>
                        <td class="px-5 py-2.5 text-ink-mute">{{ $m->recordedBy?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-6 text-center text-ink-mute">No stock movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
