<div class="space-y-4">
    <p class="text-[13px] text-ink-mute max-w-3xl">Can't find a product in the catalogue? Send its name, company and pack size with two clear photos of the label. The catalogue team builds the product from the label and links it to your stock, usually within a day. Search <a href="{{ route('portal.inventory.add') }}" class="text-primary hover:text-primary-deep">Add from catalogue</a> first.</p>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <h2 class="text-[15px] font-medium mb-3">Request a product</h2>
        <form wire:submit="submit" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <x-ui.input wire:model.live.debounce.400ms="name" label="Product name (as on the pack)" id="pr_name" :error="$errors->first('name')" />
                </div>
                <x-ui.input wire:model="pack_size" label="Pack size" id="pr_pack" placeholder="e.g. 100 g sachet, 50 kg bag" :error="$errors->first('pack_size')" />
            </div>

            @if($matches->isNotEmpty())
                <div class="rounded-[14px] border border-lemon/40 bg-canvas-cream px-4 py-3">
                    <p class="text-[13.5px] font-semibold text-ink">Is it one of these? They're already in the catalogue.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach($matches as $m)
                            <a href="{{ route('portal.inventory.add', ['search' => $m->name]) }}" class="flex items-center gap-3 rounded-[10px] bg-canvas border border-hairline px-3 py-2 hover:border-primary/40">
                                @if($m->imageUrl())<img src="{{ $m->imageUrl() }}" alt="" class="h-10 w-10 rounded object-contain bg-white">@endif
                                <span class="min-w-0 text-[13px]"><span class="block font-medium text-ink truncate">{{ $m->name }}</span><span class="block text-ink-mute truncate">{{ $m->producer?->name ?? $m->brand }} · {{ $m->variants->pluck('name')->join(', ') }}</span></span>
                                <span class="ml-auto text-[12px] text-primary shrink-0">Add this →</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <x-ui.select wire:model.live="producer_id" label="Company (producer)" id="pr_producer" :error="$errors->first('producer_id')">
                    <option value="">Not listed: type it →</option>
                    @foreach($producers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </x-ui.select>
                @if($producer_id === '')
                    <x-ui.input wire:model="producer_name" label="Company name" id="pr_producer_name" :error="$errors->first('producer_name')" />
                @endif
                <x-ui.input wire:model="notes" label="Note (optional)" id="pr_notes" placeholder="Anything the team should know" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach(['front' => 'Front of the pack', 'back' => 'Back of the pack (directions, ingredients)'] as $side => $label)
                    <div>
                        <label class="block text-[13px] text-ink-mute mb-1.5" for="pr_{{ $side }}">{{ $label }}</label>
                        <input type="file" wire:model="{{ $side }}" id="pr_{{ $side }}" accept="image/*" capture="environment" class="block w-full text-[13px] text-ink-secondary file:mr-3 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                        @error($side) <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                        @if($$side && method_exists($$side, 'temporaryUrl'))
                            <img src="{{ $$side->temporaryUrl() }}" alt="" class="mt-2 h-28 rounded border border-hairline object-contain bg-white">
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="rounded-[12px] border border-hairline p-3">
                <label class="flex items-center gap-2 text-[14px]">
                    <input type="checkbox" wire:model.live="addStock" class="rounded border-hairline-input text-primary focus:ring-primary"> Also add it to my stock now (sold in the shop until it's in the catalogue)
                </label>
                @if($addStock)
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <x-ui.select wire:model="branch_id" label="Store" id="pr_store" :error="$errors->first('branch_id')">
                            @foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </x-ui.select>
                        <x-ui.input type="number" min="0" step="any" wire:model="unit_price" label="Selling price (TZS)" id="pr_price" :error="$errors->first('unit_price')" />
                        <x-ui.input type="number" min="0" step="any" wire:model="quantity" label="Quantity" id="pr_qty" :error="$errors->first('quantity')" />
                    </div>
                @endif
            </div>

            <x-ui.button type="submit" target="submit">Send request</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-3 border-b border-hairline"><h2 class="text-[14px] text-ink-secondary">Your requests</h2></div>
        <div class="divide-y divide-hairline">
            @forelse($requests as $r)
                <div class="px-5 py-3 flex flex-wrap items-center gap-3 text-[13px]">
                    @if($r->photoUrl('label_front'))<img src="{{ $r->photoUrl('label_front') }}" alt="" class="h-10 w-10 rounded object-cover border border-hairline">@endif
                    <div class="min-w-0 flex-1">
                        <p class="text-ink font-medium">{{ $r->name }} <span class="font-normal text-ink-mute">· {{ $r->producerLabel() }} · {{ $r->pack_size }}</span></p>
                        @if($r->product)<p class="text-ink-mute">In the catalogue as "{{ $r->product->name }}"</p>@endif
                        @if($r->status === 'rejected' && $r->review_notes)<p class="text-ruby">{{ $r->review_notes }}</p>@endif
                    </div>
                    <x-ui.badge :tone="match($r->status) { 'pending' => 'warning', 'rejected' => 'danger', default => 'success' }">{{ $r->statusLabel() }}</x-ui.badge>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-ink-mute">No requests yet.</p>
            @endforelse
        </div>
    </x-ui.card>
</div>
