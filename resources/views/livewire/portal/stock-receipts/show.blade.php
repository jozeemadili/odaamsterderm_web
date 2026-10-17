<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-[15px] text-ink-secondary">Receipt #{{ $receipt->id }}</h2>
                <x-ui.badge :tone="$receipt->status === 'approved' ? 'success' : ($receipt->status === 'rejected' ? 'danger' : 'warning')">{{ $receipt->status }}</x-ui.badge>
            </div>
            @can('approve-stock-receipts')
                @if($receipt->isPending())
                    <div class="flex gap-2">
                        <x-ui.button variant="danger" size="sm" wire:click="reject" target="reject" wire:confirm="Reject this stock receipt? No inventory changes will be made.">Reject</x-ui.button>
                        <x-ui.button variant="primary" size="sm" wire:click="approve" target="approve" wire:confirm="Approve and post this receipt? Inventory quantities will increase immediately.">Approve &amp; post stock</x-ui.button>
                    </div>
                @endif
            @endcan
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-5 text-[13px]">
            <div><p class="text-ink-mute mb-1">Supplier</p><p class="text-ink font-medium">{{ $receipt->supplier?->name ?: '—' }}</p></div>
            <div><p class="text-ink-mute mb-1">Reference</p><p class="text-ink font-medium">{{ $receipt->reference_no ?: '—' }}</p></div>
            <div><p class="text-ink-mute mb-1">Date</p><p class="text-ink font-medium">{{ $receipt->receipt_date->format('d M Y') }}</p></div>
            <div><p class="text-ink-mute mb-1">Total</p><p class="text-ink font-medium tnum">TZS {{ number_format($receipt->total_amount, 0) }}</p></div>
        </div>
    </x-ui.card>

    @if($receipt->isPending())
        <x-ui.card>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] text-ink-secondary">Add product</h2>
                <button wire:click="$toggle('addingNewProduct')" class="text-[13px] text-primary-deep hover:underline">
                    {{ $addingNewProduct ? 'Choose existing product instead' : '+ This is a new product' }}
                </button>
            </div>

            @if($addingNewProduct)
                <form wire:submit="addNewProduct" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <x-ui.input wire:model="new_name" label="Product name" id="new_name" />
                    <x-ui.input wire:model="new_sku" label="SKU" id="new_sku" />
                    <x-ui.input wire:model="new_unit" label="Unit" placeholder="pcs, kg..." id="new_unit" />
                    <x-ui.input type="number" step="0.01" wire:model="quantity" label="Qty received" id="new_qty" />
                    <x-ui.input type="number" step="0.01" wire:model="unit_cost" label="Unit cost" id="new_cost" />
                    <x-ui.input type="number" step="0.01" wire:model="new_unit_price" label="Selling price" id="new_price" />
                    <x-ui.select wire:model="new_branch_id" label="Store / branch" id="new_branch">
                        <option value="">Not assigned</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="md:col-span-6">
                        <x-ui.button type="submit" target="addNewProduct">Add product to receipt</x-ui.button>
                    </div>
                </form>
                @error('new_name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            @else
                <form wire:submit="addExistingItem" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-1 relative">
                        <label class="block text-[13px] text-ink-mute mb-1.5">Product</label>
                        @if($selectedProductLabel)
                            <div class="flex items-center justify-between rounded-sm border border-hairline-input bg-canvas-soft text-[14px] px-3 py-2">
                                <span class="text-ink">{{ $selectedProductLabel }}</span>
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
                    </div>
                    <x-ui.input type="number" step="0.01" wire:model="quantity" label="Qty received" id="existing_qty" />
                    <x-ui.input type="number" step="0.01" wire:model="unit_cost" label="Unit cost" id="existing_cost" />
                    <x-ui.button type="submit" target="addExistingItem">Add to receipt</x-ui.button>
                </form>
                @error('inventory_item_id') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            @endif
        </x-ui.card>

        <x-ui.card>
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-[15px] text-ink-secondary">Add many products at once</h2>
                <button wire:click="$toggle('showImportForm')" class="text-[13px] text-primary-deep hover:underline">
                    {{ $showImportForm ? 'Cancel' : '+ Import from Excel' }}
                </button>
            </div>

            @if($showImportForm)
                <p class="text-[12px] text-ink-mute mb-4 mt-2">
                    Barcode is how we tell products apart — a row with a barcode that already exists on an existing product adds a line for that product, so you won't get duplicates. Leave Barcode blank on a new product and we'll generate one for you. Any existing product that doesn't have a barcode yet gets one assigned automatically when you download the current products file below. Inventory quantities only change once this receipt is approved.
                </p>

                <div class="flex flex-wrap gap-3 mb-4">
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="downloadEmptyTemplate" target="downloadEmptyTemplate">Download empty template</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="downloadCurrentProducts" target="downloadCurrentProducts">Download current products (with barcodes)</x-ui.button>
                </div>

                <form wire:submit="importExcel" class="space-y-2">
                    <label class="block text-[13px] text-ink-mute mb-1.5">Upload filled-in spreadsheet</label>
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls" class="text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                    @error('importFile') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="importFile" class="text-[12px] text-ink-mute">Uploading…</div>
                    <div>
                        <x-ui.button type="submit" target="importExcel">Upload &amp; import</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card>
        <h3 class="text-[15px] text-ink-secondary mb-3">Supplier documents (proforma invoice, etc.)</h3>

        @if($receipt->isPending())
            <div class="flex items-end gap-3 mb-3">
                <div class="flex-1">
                    <input type="file" wire:model="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                    @error('document') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="document" class="text-[12px] text-ink-mute mt-1">Uploading…</div>
                </div>
                <x-ui.button size="sm" wire:click="uploadDocument" target="uploadDocument">Upload</x-ui.button>
            </div>
        @endif

        @if($documents->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach($documents as $doc)
                    <li class="flex items-center justify-between text-[13px] px-3 py-1.5 rounded bg-canvas-soft">
                        <a href="{{ $doc->getUrl() }}" target="_blank" class="text-primary hover:text-primary-deep truncate">{{ $doc->file_name }}</a>
                        @if($receipt->isPending())
                            <button wire:click="removeDocument({{ $doc->id }})" wire:confirm="Remove this document?" class="text-ink-mute hover:text-ruby text-[12px] shrink-0 ml-3">Remove</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-[13px] text-ink-mute">No documents uploaded yet.</p>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Qty</th>
                    <th class="px-5 py-3 font-normal">Unit cost</th>
                    <th class="px-5 py-3 font-normal">Subtotal</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($lines as $line)
                    <tr>
                        <td class="px-5 py-3 text-ink font-medium">{{ $line->inventoryItem->name }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ rtrim(rtrim(number_format($line->quantity, 2, '.', ''), '0'), '.') }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ number_format($line->unit_cost, 0) }}</td>
                        <td class="px-5 py-3 tnum text-ink">{{ number_format($line->subtotal, 0) }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($receipt->isPending())
                                <button wire:click="removeItem({{ $line->id }})" class="text-ruby text-[12px] hover:underline">Remove</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No products added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
