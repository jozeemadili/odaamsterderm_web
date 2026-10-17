{{-- Requires the PicksCatalogProduct trait on the component. --}}
<div>
    <label class="block text-[13px] text-ink-mute mb-1.5" for="catalog_search">Catalog product <span class="text-[12px]">(needed to sell on the marketplace)</span></label>
    @if($product_id)
        <div class="flex items-center justify-between gap-3 rounded-sm border border-primary/30 bg-primary-subtle/20 px-3 py-2 text-[14px]">
            <span class="text-ink">{{ $productLabel }}</span>
            <button type="button" wire:click="clearCatalogProduct" class="text-[12px] text-ink-mute hover:text-ruby">Change</button>
        </div>
    @else
        <input type="text" wire:model.live.debounce.300ms="catalogSearch" id="catalog_search" autocomplete="off"
            placeholder="Search the catalog, e.g. Chicken Starter"
            class="w-full rounded-sm border border-hairline-input bg-canvas text-ink text-[15px] px-3 py-2 placeholder:text-ink-mute/60 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition">
        @if(count($catalogMatches) > 0)
            <div class="mt-2 border border-hairline rounded-md divide-y divide-hairline overflow-hidden">
                @foreach($catalogMatches as $match)
                    <button type="button" wire:click="selectCatalogProduct({{ $match['id'] }})" class="w-full flex items-center justify-between px-3 py-2 text-left text-[13px] hover:bg-canvas-soft">
                        <span class="text-ink">{{ $match['label'] }}</span>
                        <span class="text-ink-mute text-[12px]">
                            {{ $match['animal'] }} · {{ $match['unit'] }}
                            @if($match['status'] === 'pending') · <span class="text-lemon">pending approval</span> @endif
                        </span>
                    </button>
                @endforeach
            </div>
        @elseif(mb_strlen(trim($catalogSearch)) >= 2)
            <p class="text-[12px] text-ink-mute mt-1">Not in the catalog? <a href="{{ route('portal.catalog.index', ['propose' => 1]) }}" class="text-primary hover:text-primary-deep">Propose it</a> — an admin reviews new products before they go on the marketplace.</p>
        @endif
    @endif
    @error('product_id') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
</div>
