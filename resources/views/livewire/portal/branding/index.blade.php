@php
    $main = preg_match('/^#[0-9A-Fa-f]{6}$/', $brand_color) ? $brand_color : $merchant->brandColor();
    $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', $brand_accent_color) ? $brand_accent_color : $merchant->accentColor();
    $mainInk = \App\Models\Merchant::textColorOn($main);
    $accentInk = \App\Models\Merchant::textColorOn($accent);
@endphp
<div class="space-y-4 max-w-4xl">
    <p class="text-[13px] text-ink-mute max-w-2xl">Your logo and two brand colours appear on your <strong>receipts</strong>, <strong>invoices</strong>, your <strong>seller portal</strong> and app, and your <strong>store page</strong> on the marketplace — so customers and staff see your brand.</p>

    <x-ui.card>
        <h2 class="text-[15px] text-ink-secondary mb-1">Business logo</h2>
        <p class="text-[12px] text-ink-mute mb-4">A square logo on a plain background works best (PNG or JPG, up to 2 MB).</p>

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
        <h2 class="text-[15px] text-ink-secondary mb-1">Brand colours</h2>
        <p class="text-[12px] text-ink-mute mb-4">Pick a <strong>main colour</strong> (headers, your portal menu) and an <strong>accent colour</strong> (highlights, totals, buttons). Start from a ready-made pair or choose your own.</p>

        <div class="flex flex-wrap gap-2 mb-5">
            @foreach(\App\Livewire\Portal\Branding\Index::PALETTES as $name => [$p1, $p2])
                <button type="button" wire:click="usePalette(@js($name))" class="inline-flex items-center gap-2 rounded-pill border border-hairline px-3 py-1.5 text-[12px] hover:border-primary/50">
                    <span class="h-4 w-4 rounded-full border border-black/10" style="background: {{ $p1 }}"></span>
                    <span class="h-4 w-4 -ml-3 rounded-full border border-white" style="background: {{ $p2 }}"></span>
                    {{ $name }}
                </button>
            @endforeach
        </div>

        <form wire:submit="saveColor" class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-[13px] text-ink-mute mb-1.5" for="brand_color_hex">Main colour</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model.live="brand_color" class="h-10 w-14 rounded border border-hairline-input cursor-pointer" aria-label="Main colour picker">
                    <x-ui.input wire:model.live.debounce.400ms="brand_color" placeholder="#01162F" id="brand_color_hex" class="w-32" />
                </div>
                @error('brand_color') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[13px] text-ink-mute mb-1.5" for="brand_accent_hex">Accent colour</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model.live="brand_accent_color" class="h-10 w-14 rounded border border-hairline-input cursor-pointer" aria-label="Accent colour picker">
                    <x-ui.input wire:model.live.debounce.400ms="brand_accent_color" placeholder="#C9972E" id="brand_accent_hex" class="w-32" />
                </div>
                @error('brand_accent_color') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2 flex flex-wrap gap-2">
                <x-ui.button type="submit" size="sm" target="saveColor">Save colours</x-ui.button>
                @if($merchant->brand_color || $merchant->brand_accent_color)
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="resetColor" target="resetColor">Use Odaamsterderm Hub colours</x-ui.button>
                @endif
            </div>
        </form>

        <p class="text-[12px] text-ink-mute mt-6 mb-2">Preview</p>
        <div class="grid sm:grid-cols-2 gap-4">
            {{-- Receipt preview --}}
            <div class="rounded-xl overflow-hidden border border-hairline">
                <div class="px-4 py-3 flex items-center gap-3" style="background: {{ $main }}; color: {{ $mainInk }}">
                    @if($merchant->logo())<img src="{{ $merchant->logo()->getUrl() }}" alt="" class="h-9 w-9 rounded bg-white object-contain p-0.5">@endif
                    <div><p class="text-[14px] font-semibold">{{ $merchant->business_name }}</p><p class="text-[11px] opacity-80">Order receipt · OH-2610-DEMO</p></div>
                </div>
                <div class="h-1.5" style="background: {{ $accent }}"></div>
                <div class="px-4 py-3 text-[12px] space-y-1.5">
                    <div class="flex justify-between"><span>2 × Chicken Starter 25KG</span><span class="tnum">TZS 90,000</span></div>
                    <div class="flex justify-between font-semibold text-[14px] pt-2 border-t-2" style="border-color: {{ $accent }}"><span>Total</span><span class="tnum">TZS 90,000</span></div>
                    <span class="inline-block rounded-pill px-2 py-0.5 text-[11px] font-semibold" style="background: {{ $accent }}; color: {{ $accentInk }}">Ready for pickup</span>
                </div>
            </div>
            {{-- Portal / app preview --}}
            <div class="rounded-xl overflow-hidden border border-hairline flex">
                <div class="w-28 p-3 space-y-2 text-[11px]" style="background: {{ $main }}; color: {{ $mainInk }}">
                    <p class="font-semibold truncate">{{ $merchant->business_name }}</p>
                    <p class="rounded px-2 py-1" style="background: {{ $accent }}; color: {{ $accentInk }}">Dashboard</p>
                    <p class="px-2 opacity-80">Inventory</p>
                    <p class="px-2 opacity-80">Orders</p>
                </div>
                <div class="flex-1 p-3 text-[12px] text-ink-mute">
                    Your portal menu and app use these colours.
                    <button type="button" class="mt-3 block rounded-pill px-3 py-1.5 text-[12px] font-semibold" style="background: {{ $accent }}; color: {{ $accentInk }}">Example button</button>
                </div>
            </div>
        </div>
    </x-ui.card>
</div>
