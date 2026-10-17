<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between flex-wrap gap-3 mb-2">
            <div>
                <h2 class="text-[15px] text-ink-secondary">Print barcode labels</h2>
                <p class="text-[12px] text-ink-mute mt-1">Select products, set how many copies of each label you need, then download one PDF to print and stick on your physical products.</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <x-ui.button variant="secondary" size="sm" wire:click="selectAll">Select all</x-ui.button>
                <x-ui.button variant="secondary" size="sm" wire:click="selectNone">Select none</x-ui.button>
                <x-ui.button size="sm" wire:click="downloadPdf" target="downloadPdf">Download PDF</x-ui.button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 mt-3 pt-3 border-t border-hairline">
            <span class="text-[12px] text-ink-mute">Every label always shows your shop name and logo (if set) plus the barcode. Choose what else to include:</span>
            <label class="flex items-center gap-1.5 text-[12px] text-ink-secondary">
                <input type="checkbox" wire:model="showProductName" class="rounded border-hairline-input">
                Product name
            </label>
            <label class="flex items-center gap-1.5 text-[12px] text-ink-secondary">
                <input type="checkbox" wire:model="showPrice" class="rounded border-hairline-input">
                Price
            </label>
        </div>

        @if($withoutBarcodeCount > 0)
            <div class="mt-3 rounded-lg bg-canvas-cream border border-lemon/20 text-lemon px-4 py-2.5 text-[13px]">
                {{ $withoutBarcodeCount }} product{{ $withoutBarcodeCount > 1 ? 's have' : ' has' }} no barcode assigned yet, so {{ $withoutBarcodeCount > 1 ? "they aren't" : "it isn't" }} listed here. Add a barcode from the product's page first.
            </div>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal w-10"></th>
                    <th class="px-5 py-3 font-normal">Item</th>
                    <th class="px-5 py-3 font-normal">Barcode</th>
                    <th class="px-5 py-3 font-normal">Price</th>
                    <th class="px-5 py-3 font-normal w-32">Copies</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($items as $item)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3">
                            <input type="checkbox" wire:model="selected.{{ $item->id }}" class="rounded border-hairline-input">
                        </td>
                        <td class="px-5 py-3 text-ink font-medium">
                            <div class="flex items-center gap-2.5">
                                @if($item->image())
                                    <img src="{{ $item->image()->getUrl() }}" alt="" class="h-8 w-8 rounded object-cover border border-hairline">
                                @else
                                    <span class="h-8 w-8 rounded bg-canvas-soft border border-hairline flex items-center justify-center text-ink-mute text-[10px]">—</span>
                                @endif
                                {{ $item->name }}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary tnum">{{ $item->barcode }}</td>
                        <td class="px-5 py-3 text-ink-secondary tnum">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</td>
                        <td class="px-5 py-3">
                            <input type="number" min="1" wire:model="copies.{{ $item->id }}" class="w-20 rounded-sm border border-hairline-input bg-canvas text-ink text-[13px] px-2 py-1">
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No products with a barcode yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
