<div>
    @if($stores->count() > 1)
        <label class="block px-2 mb-3">
            <span class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Working in</span>
            <select wire:model.live="selected" class="w-full rounded-lg border-0 bg-white/10 text-white text-[13px] py-1.5 pl-2 pr-7 focus:ring-1 focus:ring-white/40 [&>option]:text-ink">
                <option value="">All my stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </select>
        </label>
    @elseif($stores->count() === 1)
        <p class="px-2 mb-3 text-[12px] text-white/60 truncate">Store: <span class="text-white">{{ $stores->first()->name }}</span></p>
    @endif
</div>
