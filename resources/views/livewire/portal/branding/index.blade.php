<div class="space-y-4 max-w-2xl">
    <x-ui.card>
        <h2 class="text-[15px] text-ink-secondary mb-1">Business logo</h2>
        <p class="text-[12px] text-ink-mute mb-4">Shown on your invoices and proforma PDFs.</p>

        <div class="flex items-center gap-5">
            @if($merchant->logo())
                <img src="{{ $merchant->logo()->getUrl() }}" alt="{{ $merchant->business_name }}" class="h-16 w-16 rounded-lg object-contain border border-hairline bg-canvas-soft p-1">
            @else
                <div class="h-16 w-16 rounded-lg border border-dashed border-hairline flex items-center justify-center text-ink-mute text-[11px] text-center px-1">No logo</div>
            @endif

            <form wire:submit="saveLogo" class="flex-1 space-y-2">
                <input type="file" wire:model="logo" accept="image/*" class="text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                @error('logo') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                <div wire:loading wire:target="logo" class="text-[12px] text-ink-mute">Uploading…</div>
                <div class="flex gap-2">
                    <x-ui.button type="submit" size="sm" target="saveLogo">Save logo</x-ui.button>
                    @if($merchant->logo())
                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="removeLogo" target="removeLogo" wire:confirm="Remove your business logo?">Remove</x-ui.button>
                    @endif
                </div>
            </form>
        </div>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-[15px] text-ink-secondary mb-1">Brand color</h2>
        <p class="text-[12px] text-ink-mute mb-4">Used for the header and accents on your invoices and proforma PDFs. Leave it as the DukaFlow default if you'd rather not customize it.</p>

        <form wire:submit="saveColor" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[13px] text-ink-mute mb-1.5">Color</label>
                <input type="color" wire:model="brand_color" class="h-10 w-16 rounded border border-hairline-input cursor-pointer">
            </div>
            <div>
                <x-ui.input wire:model="brand_color" label="Hex code" placeholder="#01162F" id="brand_color_hex" class="w-32" />
            </div>
            <x-ui.button type="submit" size="sm" target="saveColor">Save color</x-ui.button>
            @if($merchant->brand_color)
                <x-ui.button type="button" variant="secondary" size="sm" wire:click="resetColor" target="resetColor">Reset to DukaFlow default</x-ui.button>
            @endif
        </form>
        @error('brand_color') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror

        <div class="mt-5 rounded-lg overflow-hidden border border-hairline">
            <div class="px-4 py-3 text-white text-[13px] font-medium" style="background-color: {{ $brand_color ?: $merchant->brandColor() }};">
                Sample invoice header
            </div>
            <div class="px-4 py-2.5 text-[12px] text-ink-mute">This is how the accent color will look on your documents.</div>
        </div>
    </x-ui.card>
</div>
