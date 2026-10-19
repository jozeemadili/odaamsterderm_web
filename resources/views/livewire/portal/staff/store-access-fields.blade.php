{{-- Store access: all stores, or the ticked ones. Expects $stores. --}}
<div class="space-y-2">
    <p class="text-[13px] text-ink-mute">Store access</p>
    <div class="flex flex-wrap gap-4 text-[13px]">
        <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="storeAccess" value="selected" class="text-primary focus:ring-primary"> Selected stores</label>
        <label class="inline-flex items-center gap-2"><input type="radio" wire:model.live="storeAccess" value="all" class="text-primary focus:ring-primary"> All stores (now and future)</label>
    </div>
    @if($storeAccess === 'selected')
        <div class="flex flex-wrap gap-3">
            @forelse($stores as $store)
                <label class="inline-flex items-center gap-2 rounded-pill border border-hairline px-3 py-1.5 text-[13px]">
                    <input type="checkbox" wire:model="storeIds" value="{{ $store->id }}" class="rounded border-hairline-input text-primary focus:ring-primary">
                    {{ $store->name }}
                </label>
            @empty
                <p class="text-[12px] text-ink-mute">Add a store first (Stores page).</p>
            @endforelse
        </div>
    @endif
    @error('storeIds') <p class="text-ruby text-[12px]">Choose at least one store.</p> @enderror
</div>
