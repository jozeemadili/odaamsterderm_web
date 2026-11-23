<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">The companies that make catalogue products. Buyers see “Chicken Grower 25KG <strong class="text-ink">by Silverlands</strong>” with the logo. The same product from two producers is two catalogue products.</p>
        <div class="flex shrink-0 items-center gap-2">
            @include('livewire.admin.partials.excel-button')
            <x-ui.button wire:click="startCreating">+ Add producer</x-ui.button>
        </div>
    </div>

    @include('livewire.admin.partials.excel-panel', ['what' => 'producers', 'images' => 'logos'])

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    {{-- At a glance; each tile filters the list --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach([
            '' => ['Producers', $stats['all'], 'text-ink'],
            'active' => ['Active', $stats['active'], 'text-success'],
            'hidden' => ['Hidden on marketplace', $stats['hidden'], $stats['hidden'] ? 'text-ink' : 'text-ink-mute'],
            'no_logo' => ['Without a logo', $stats['no_logo'], $stats['no_logo'] ? 'text-ruby' : 'text-ink-mute'],
        ] as $key => [$label, $count, $tone])
            <button type="button" wire:click="$set('filter', '{{ $filter === $key && $key !== '' ? '' : $key }}')"
                class="rounded-[16px] border bg-canvas px-4 py-3 text-left transition hover:border-primary/40 {{ $filter === $key ? 'border-primary ring-1 ring-primary/30' : 'border-hairline' }}">
                <span class="block font-display text-[24px] font-bold leading-none tnum {{ $tone }}">{{ $count }}</span>
                <span class="mt-1 block text-[12.5px] text-ink-mute">{{ $label }}</span>
            </button>
        @endforeach
    </div>

    @if($showForm)
        <x-ui.card>
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-[15px] font-semibold text-ink">{{ $editingId ? 'Edit producer' : 'New producer' }}</h2>
                <button type="button" wire:click="$set('showForm', false)" class="text-[13px] text-ink-mute hover:text-ink">Close</button>
            </div>
            <form wire:submit="save" class="grid grid-cols-1 gap-5 md:grid-cols-[140px_1fr]">
                {{-- Logo --}}
                <div>
                    @php($current = $editingId ? \App\Models\Producer::find($editingId)?->logoUrl() : null)
                    <label for="producer_logo" class="flex h-32 w-32 cursor-pointer items-center justify-center overflow-hidden rounded-[20px] border border-dashed border-hairline bg-white hover:border-primary/50">
                        @if($logo && method_exists($logo, 'temporaryUrl'))
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-full w-full object-contain p-2">
                        @elseif($current)
                            <img src="{{ $current }}" alt="" class="h-full w-full object-contain p-2">
                        @else
                            <span class="px-3 text-center text-[12px] text-ink-mute">Add logo<br><span class="text-[11px]">square, PNG/JPG</span></span>
                        @endif
                    </label>
                    <input type="file" wire:model="logo" id="producer_logo" accept="image/*" class="sr-only">
                    <span wire:loading wire:target="logo" class="mt-1 block text-[11.5px] text-ink-mute">Uploading…</span>
                    @error('logo') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="name" label="Name" id="producer_name" placeholder="e.g. Silverlands Tanzania" :error="$errors->first('name')" />
                    </div>
                    <x-ui.input wire:model="country" label="Country" id="producer_country" :error="$errors->first('country')" />
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="role" label="What they make" id="producer_role" placeholder="e.g. Manufacturer (veterinary medicines)" :error="$errors->first('role')" />
                    </div>
                    <x-ui.select wire:model="deals_in" label="Hatchery (day-old chicks)" id="producer_deals" :error="$errors->first('deals_in')">
                        <option value="">Not a hatchery</option>
                        @foreach(\App\Models\Producer::DEALS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="website" label="Website" id="producer_website" placeholder="https://…" :error="$errors->first('website')" />
                    <x-ui.input wire:model="phone" label="Phone" id="producer_phone" :error="$errors->first('phone')" />
                    <x-ui.input wire:model="location" label="Location" id="producer_location" placeholder="Town or region" :error="$errors->first('location')" />
                    <div class="md:col-span-3">
                        <x-ui.textarea wire:model="description" label="About (shown to customers)" id="producer_description" rows="2" :error="$errors->first('description')"></x-ui.textarea>
                    </div>
                    <div class="md:col-span-3 flex gap-2">
                        <x-ui.button type="submit" target="save">{{ $editingId ? 'Save changes' : 'Add producer' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full max-w-sm">
            <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search name, country or what they make…" id="producer_search" />
        </div>
        @if($filter !== '')
            <button type="button" wire:click="$set('filter', '')" class="rounded-pill bg-canvas-soft px-3 py-1 text-[12.5px] text-ink-secondary hover:text-ink">Showing: {{ ['active' => 'Active', 'inactive' => 'Inactive', 'hidden' => 'Hidden on marketplace', 'no_logo' => 'Without a logo'][$filter] ?? $filter }} ✕</button>
        @endif
        <span class="ml-auto text-[12.5px] text-ink-mute">{{ $producers->count() }} shown</span>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($producers as $producer)
            <div wire:key="producer-{{ $producer->id }}" class="flex flex-col rounded-[18px] border border-hairline bg-canvas p-4 transition hover:border-primary/30 {{ $producer->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.producers.show', $producer) }}" class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[14px] border border-hairline bg-white hover:border-primary/40">
                        @if($producer->logoUrl())
                            <img src="{{ $producer->logoUrl() }}" alt="" class="h-full w-full object-contain p-1">
                        @else
                            <span class="font-display text-[15px] font-bold text-primary">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($producer->name, 0, 2)) }}</span>
                        @endif
                    </a>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.producers.show', $producer) }}" class="block truncate text-[14.5px] font-semibold text-ink hover:text-primary" title="{{ $producer->name }}">{{ $producer->name }}</a>
                        <p class="truncate text-[12px] text-ink-mute">{{ collect([$producer->location, $producer->country])->filter()->unique()->join(', ') ?: '—' }}@if($producer->code) · {{ $producer->code }}@endif</p>
                        @if($producer->role)<p class="mt-0.5 line-clamp-2 text-[12px] text-ink-secondary">{{ $producer->role }}</p>@endif
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11.5px]">
                    <span class="rounded-pill bg-canvas-soft px-2 py-0.5 font-semibold text-ink tnum">{{ $producer->products_count }} {{ \Illuminate\Support\Str::plural('product', $producer->products_count) }}</span>
                    @if($producer->deals_in)<span class="rounded-pill bg-canvas-cream px-2 py-0.5 text-ink">Hatchery · {{ \App\Models\Producer::DEALS[$producer->deals_in] ?? $producer->deals_in }}</span>@endif
                    @unless($producer->is_active)<span class="rounded-pill bg-canvas-soft px-2 py-0.5 text-ink-mute">Inactive</span>@endunless
                    @unless($producer->on_marketplace)<span class="rounded-pill bg-ruby/10 px-2 py-0.5 text-ruby">Hidden on marketplace</span>@endunless
                    @unless($producer->logoUrl())<span class="rounded-pill border border-dashed border-hairline px-2 py-0.5 text-ink-mute">No logo</span>@endunless
                </div>
                <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-hairline pt-3 mt-3 text-[12px]">
                    <a href="{{ route('admin.producers.show', $producer) }}" class="inline-flex items-center rounded-pill bg-primary px-3 py-1 font-semibold text-white hover:bg-primary-deep">View more →</a>
                    <button wire:click="startEditing({{ $producer->id }})" class="text-ink-mute hover:text-ink">Edit</button>
                    @if($producer->website)<a href="{{ $producer->website }}" target="_blank" rel="noopener" class="text-ink-mute hover:text-ink">Website ↗</a>@endif
                    <span class="ml-auto"></span>
                    @if($producer->logoUrl())
                        <button wire:click="removeLogo({{ $producer->id }})" wire:confirm="Remove {{ $producer->name }}'s logo?" class="text-ink-mute hover:text-ruby">Remove logo</button>
                    @endif
                    <button wire:click="toggleMarketplace({{ $producer->id }})" class="text-ink-mute hover:text-ink">{{ $producer->on_marketplace ? 'Hide' : 'Show' }}</button>
                    <button wire:click="toggleActive({{ $producer->id }})" class="{{ $producer->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary-deep' }}">{{ $producer->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-[18px] border border-dashed border-hairline px-5 py-12 text-center text-[13px] text-ink-mute">
                No producers match. Add one, or upload many at once with Excel.
            </div>
        @endforelse
    </div>
</div>
