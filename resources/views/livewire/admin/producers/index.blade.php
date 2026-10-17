<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">Producers are the factories and companies that make catalog products. Customers see “Chicken Grower 25KG <strong>by Silverlands</strong>” with the logo, and sellers know exactly which product they stock. The same product from two producers is two catalog products.</p>
        <x-ui.button wire:click="startCreating">+ Add producer</x-ui.button>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @if($showForm)
        <x-ui.card>
            <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <x-ui.input wire:model="name" label="Name" id="producer_name" placeholder="e.g. Silverlands" :error="$errors->first('name')" />
                </div>
                <x-ui.input wire:model="country" label="Country" id="producer_country" :error="$errors->first('country')" />
                <x-ui.input wire:model="website" label="Website (optional)" id="producer_website" placeholder="https://…" :error="$errors->first('website')" />
                <div class="md:col-span-3">
                    <x-ui.textarea wire:model="description" label="About (optional, shown to customers)" id="producer_description" rows="2" :error="$errors->first('description')"></x-ui.textarea>
                </div>
                <div>
                    <label class="block text-[13px] text-ink-mute mb-1.5" for="producer_logo">Logo (optional, square works best)</label>
                    <input type="file" wire:model="logo" id="producer_logo" accept="image/*" class="block w-full text-[13px] text-ink-secondary file:mr-3 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                    @error('logo') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    @if($logo && method_exists($logo, 'temporaryUrl'))
                        <img src="{{ $logo->temporaryUrl() }}" alt="" class="mt-2 h-12 w-12 rounded object-contain border border-hairline bg-white">
                    @endif
                </div>
                <div class="md:col-span-4 flex gap-2">
                    <x-ui.button type="submit" target="save">{{ $editingId ? 'Save changes' : 'Add producer' }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="max-w-sm">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search producers…" id="producer_search" />
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[720px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Producer</th>
                    <th class="px-5 py-3 font-normal">Country</th>
                    <th class="px-5 py-3 font-normal">Products</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($producers as $producer)
                    <tr class="hover:bg-canvas-soft/60" wire:key="producer-{{ $producer->id }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                @if($producer->logoUrl())
                                    <img src="{{ $producer->logoUrl() }}" alt="" class="h-9 w-9 rounded object-contain border border-hairline bg-white">
                                @else
                                    <span class="h-9 w-9 rounded border border-dashed border-hairline flex items-center justify-center text-[11px] text-ink-mute">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($producer->name, 0, 2)) }}</span>
                                @endif
                                <div>
                                    <p class="text-ink font-medium">{{ $producer->name }}</p>
                                    @if($producer->website)<a href="{{ $producer->website }}" target="_blank" rel="noopener" class="text-[12px] text-ink-mute hover:text-primary-deep">{{ parse_url($producer->website, PHP_URL_HOST) ?: $producer->website }}</a>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $producer->country ?: '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $producer->products_count }}</td>
                        <td class="px-5 py-3"><x-ui.badge :tone="$producer->is_active ? 'success' : 'neutral'">{{ $producer->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button wire:click="startEditing({{ $producer->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">Edit</button>
                            @if($producer->logoUrl())
                                <button wire:click="removeLogo({{ $producer->id }})" class="text-[12px] text-ink-mute hover:text-ruby mr-3">Remove logo</button>
                            @endif
                            <button wire:click="toggleActive({{ $producer->id }})" class="text-[12px] {{ $producer->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary-deep' }}">{{ $producer->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-ink-mute">No producers yet. Add the factories and companies whose products are sold here.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>
</div>
