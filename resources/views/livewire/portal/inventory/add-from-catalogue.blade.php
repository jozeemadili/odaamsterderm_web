<div class="space-y-4 pb-24">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-3xl">Pick what's on your shelf from the shared catalogue: choose the companies you buy from, or search. Tick each pack size you stock and enter your price and quantity. Product details, photos and use rates come from the catalogue.</p>
        <a href="{{ route('portal.product-requests.index') }}" class="text-[13px] font-medium text-primary hover:text-primary-deep">Can't find a product? Request it →</a>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <x-ui.select wire:model="branchId" label="Add to store" id="pick_store">
                @foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </x-ui.select>
            <x-ui.input wire:model.live.debounce.300ms="search" label="Search" id="pick_search" placeholder="Name, brand or Kiswahili (e.g. dawa ya minyoo)" />
            <x-ui.select wire:model.live="animal" label="Animal" id="pick_animal">
                <option value="">All animals</option>
                @foreach($animals as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
            </x-ui.select>
        </div>
        <div class="mt-4">
            <p class="text-[12.5px] text-ink-mute mb-2">Companies you buy from</p>
            <div class="flex flex-wrap gap-2">
                @foreach($producerChips as $p)
                    @php($on = in_array((string) $p->id, $producers, true))
                    <button type="button" wire:click="toggleProducer({{ $p->id }})" class="rounded-pill border px-3 py-1.5 text-[13px] font-medium transition {{ $on ? 'border-primary bg-primary text-white' : 'border-hairline bg-canvas text-ink-secondary hover:border-primary/40' }}">{{ $p->name }} <span class="{{ $on ? 'text-white/80' : 'text-ink-mute' }}">{{ $p->products_count }}</span></button>
                @endforeach
            </div>
        </div>
    </x-ui.card>

    @error('lines') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror

    <x-ui.card padding="p-0">
        <div class="px-4 py-2.5 border-b border-hairline text-[12.5px] text-ink-mute">
            {{ $search === '' && ! $producers ? 'Most-stocked products first' : $products->count().' '.\Illuminate\Support\Str::plural('product', $products->count()) }}
        </div>
        <div class="divide-y divide-hairline">
            @forelse($products as $p)
                <div class="px-4 py-3 flex flex-wrap gap-4" wire:key="pp-{{ $p->id }}">
                    <div class="flex items-center gap-3 min-w-[240px] flex-1">
                        @if($p->imageUrl())
                            <img src="{{ $p->imageUrl() }}" alt="" class="h-12 w-12 rounded object-contain border border-hairline bg-white">
                        @else
                            <span class="h-12 w-12 rounded bg-canvas-soft border border-hairline"></span>
                        @endif
                        <div class="min-w-0">
                            <p class="font-medium text-ink">{{ $p->name }}</p>
                            <p class="text-[12px] text-ink-mute">{{ $p->producer?->name ?? $p->brand }} · {{ $p->category?->name }}@if($p->agents_count) · stocked by {{ $p->agents_count }} {{ \Illuminate\Support\Str::plural('agent', $p->agents_count) }}@endif</p>
                        </div>
                    </div>
                    <div class="flex-1 min-w-[300px] space-y-1.5">
                        @forelse($p->variants as $v)
                            @php($on = ! empty($lines[$v->id]['on'] ?? false))
                            <div class="flex flex-wrap items-center gap-2 text-[13px]" wire:key="pv-{{ $v->id }}">
                                @if($v->stocked)
                                    <span class="w-40 text-ink-mute">{{ $v->name }}</span>
                                    <x-ui.badge tone="success">In your stock</x-ui.badge>
                                @else
                                    <label class="w-40 flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="lines.{{ $v->id }}.on" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $v->name }}
                                    </label>
                                    @if($on)
                                        <input type="number" min="0" step="any" wire:model="lines.{{ $v->id }}.price" placeholder="Price (TZS)" aria-label="Price for {{ $v->name }}" class="w-32 rounded-sm border border-hairline-input px-2 py-1 tnum">
                                        <input type="number" min="0" step="any" wire:model="lines.{{ $v->id }}.qty" placeholder="Qty" aria-label="Quantity of {{ $v->name }}" class="w-20 rounded-sm border border-hairline-input px-2 py-1 tnum">
                                        <span class="text-ink-mute">{{ \Illuminate\Support\Str::plural($v->selling_unit ?: 'unit') }}</span>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="text-[12px] text-ink-mute">No pack sizes listed.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-ink-mute text-[14px]">
                    Nothing matches. <a href="{{ route('portal.product-requests.index', ['name' => $search]) }}" class="text-primary hover:text-primary-deep">Request this product</a> with photos of its label.
                </div>
            @endforelse
        </div>
    </x-ui.card>

    {{-- Sticky action bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-30 border-t border-hairline bg-canvas/95 backdrop-blur px-4 py-3">
        <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-end gap-4">
            <label class="flex items-center gap-2 text-[13px] text-ink-secondary">
                <input type="checkbox" wire:model="listOnline" class="rounded border-hairline-input text-primary focus:ring-primary"> List on the marketplace
            </label>
            <x-ui.button wire:click="add" target="add" :disabled="$pickedCount === 0">Add {{ $pickedCount ?: '' }} to stock</x-ui.button>
        </div>
    </div>
</div>
