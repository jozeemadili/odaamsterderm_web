<div class="space-y-5">
    <nav class="flex items-center gap-1.5 text-[13px] text-ink-mute">
        <a href="{{ route('admin.animal-types.index') }}" class="text-primary hover:text-primary-deep">Animals</a>
        @if($animal->parent)<span>›</span><a href="{{ route('admin.animal-types.show', $animal->parent) }}" class="text-primary hover:text-primary-deep">{{ $animal->parent->shortName() }}</a>@endif
        <span>›</span><span class="text-ink">{{ $animal->name }}</span>
    </nav>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    {{-- Hero --}}
    <section class="overflow-hidden rounded-[24px] border border-hairline bg-canvas">
        <div class="grid md:grid-cols-[300px_1fr]">
            <div class="relative h-56 bg-gradient-to-br from-canvas-cream to-canvas-soft md:h-full md:min-h-[240px]">
                @if($animal->imageUrl())
                    <img src="{{ $animal->imageUrl() }}" alt="{{ $animal->name }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full items-center justify-center text-[96px]" aria-hidden="true">{{ $animal->emoji() }}</span>
                @endif
                <div class="absolute inset-x-3 bottom-3 flex flex-wrap gap-2">
                    <label for="animal_photo" class="cursor-pointer rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] font-semibold text-ink shadow-sm hover:bg-white">{{ $animal->imageUrl() ? 'Change photo' : '+ Add photo' }}</label>
                    <input type="file" id="animal_photo" wire:model="photo" accept="image/*" class="sr-only">
                    @if($animal->imageUrl())<button type="button" wire:click="removePhoto" wire:confirm="Remove this photo?" class="rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] text-ink-mute shadow-sm hover:text-ruby">Remove</button>@endif
                    <span wire:loading wire:target="photo" class="rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] text-ink-mute">Uploading…</span>
                </div>
                @error('photo') <p class="absolute left-3 top-3 rounded bg-white px-2 py-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
            </div>
            <div class="p-5 md:p-6">
                @if($editing)
                    <form wire:submit="saveDetails" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-ui.input wire:model="details.name" label="Name" id="d_name" :error="$errors->first('details.name')" />
                        <x-ui.input wire:model="details.name_sw" label="Name (Kiswahili)" id="d_name_sw" :error="$errors->first('details.name_sw')" />
                        <div class="md:col-span-2"><x-ui.textarea wire:model="details.description" label="Description" id="d_desc" rows="3" :error="$errors->first('details.description')"></x-ui.textarea></div>
                        <x-ui.input type="number" min="0" wire:model="details.sort_order" label="Sort order" id="d_sort" :error="$errors->first('details.sort_order')" />
                        <div class="flex items-end gap-2">
                            <x-ui.button type="submit" target="saveDetails">Save</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="$set('editing', false)">Cancel</x-ui.button>
                        </div>
                    </form>
                @else
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[12px] uppercase tracking-wide text-ink-mute">{{ $animal->parent ? 'Type of '.$animal->parent->shortName() : 'Animal group' }} · {{ $animal->code }}</p>
                            <h1 class="mt-1 font-display text-[28px] font-bold leading-tight text-ink">{{ $animal->name }}</h1>
                            <p class="text-[15px] text-ink-secondary">{{ $animal->name_sw ?: 'No Kiswahili name yet' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.badge :tone="$animal->is_active ? 'success' : 'neutral'">{{ $animal->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            <x-ui.button size="sm" variant="secondary" wire:click="$set('editing', true)">Edit details</x-ui.button>
                        </div>
                    </div>
                    <p class="mt-3 max-w-2xl text-[14px] text-ink-secondary">{{ $animal->description ?: 'No description yet.' }}</p>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach([['Catalogue products', $stats['products']], ['Approved', $stats['approved']], ['Verified', $stats['verified']], ['Agents stocking', $stats['sellers']]] as [$label, $count])
                            <div class="rounded-[14px] bg-canvas-soft px-3 py-2.5">
                                <span class="block font-display text-[22px] font-bold leading-none tnum text-ink">{{ $count }}</span>
                                <span class="mt-1 block text-[12px] text-ink-mute">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3 text-[12.5px]">
                        <a href="{{ route('admin.products.index', ['animalFilter' => $animal->id, 'status' => 'all']) }}" class="font-semibold text-primary hover:text-primary-deep">See its products →</a>
                        <button type="button" wire:click="toggleActive" wire:confirm="{{ $animal->is_active ? 'Deactivate '.$animal->name.'? It will be hidden from new products and marketplace filters.' : 'Reactivate '.$animal->name.'?' }}" class="{{ $animal->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $animal->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Types under a group --}}
    @unless($animal->parent)
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[17px] font-semibold text-ink">Types of {{ $animal->shortName() }}</h2>
                <button type="button" wire:click="$toggle('typeForm')" class="text-[13px] font-semibold text-primary hover:text-primary-deep">+ Add type</button>
            </div>
            @if($typeForm)
                <form wire:submit="saveType" class="flex flex-wrap items-end gap-3 rounded-[16px] border border-hairline bg-canvas p-4">
                    <x-ui.input wire:model="type.name" label="Name" id="t_name" placeholder="e.g. Layers" :error="$errors->first('type.name')" />
                    <x-ui.input wire:model="type.name_sw" label="Kiswahili" id="t_name_sw" placeholder="e.g. Kuku wa mayai" />
                    <x-ui.button type="submit" target="saveType">Add</x-ui.button>
                </form>
            @endif
            @if($animal->children->isEmpty())
                <p class="rounded-[16px] border border-dashed border-hairline px-4 py-6 text-center text-[13px] text-ink-mute">No types yet. Add one, e.g. Chicken under Poultry.</p>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach($animal->children as $child)
                        <a href="{{ route('admin.animal-types.show', $child) }}" wire:key="child-{{ $child->id }}" class="group overflow-hidden rounded-[16px] border border-hairline bg-canvas hover:border-primary/40 {{ $child->is_active ? '' : 'opacity-50' }}">
                            <span class="block h-24 bg-canvas-soft">
                                @if($child->imageUrl())<img src="{{ $child->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<span class="flex h-full items-center justify-center text-[40px]" aria-hidden="true">{{ $child->emoji() }}</span>@endif
                            </span>
                            <span class="block p-3">
                                <span class="block truncate text-[13.5px] font-semibold text-ink">{{ $child->name }}</span>
                                <span class="block text-[12px] text-ink-mute">{{ $child->name_sw ?: '—' }} · {{ $child->tagged_products_count }} products</span>
                                <span class="mt-1 block text-[12px] font-semibold text-primary group-hover:text-primary-deep">View more →</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endunless

    {{-- Growth stages --}}
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-[17px] font-semibold text-ink">Growth stages</h2>
                @if($animal->parent)<p class="text-[12.5px] text-ink-mute">Stages are shared by all {{ $owner->shortName() }} types. Changes here apply to {{ $owner->shortName() }}.</p>@endif
            </div>
            <button type="button" wire:click="newStage" class="text-[13px] font-semibold text-primary hover:text-primary-deep">+ Add stage</button>
        </div>

        @if($stageForm)
            <form wire:submit="saveStage" class="grid grid-cols-1 gap-4 rounded-[18px] border border-hairline bg-canvas p-4 md:grid-cols-[120px_1fr]">
                <div>
                    @php($editingStage = $stageId ? $stages->firstWhere('id', $stageId) : null)
                    <label for="stage_photo" class="flex h-28 w-28 cursor-pointer items-center justify-center overflow-hidden rounded-[16px] border border-dashed border-hairline bg-canvas-soft hover:border-primary/50">
                        @if($stagePhoto && method_exists($stagePhoto, 'temporaryUrl'))<img src="{{ $stagePhoto->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                        @elseif($editingStage?->imageUrl())<img src="{{ $editingStage->imageUrl() }}" alt="" class="h-full w-full object-cover">
                        @else<span class="px-2 text-center text-[12px] text-ink-mute">Add a photo</span>@endif
                    </label>
                    <input type="file" id="stage_photo" wire:model="stagePhoto" accept="image/*" class="sr-only">
                    @error('stagePhoto') <p class="mt-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <x-ui.input wire:model="stage.name" label="Stage name" id="s_name" placeholder="e.g. Starter / chick (0–4 wks)" :error="$errors->first('stage.name')" />
                    <x-ui.input wire:model="stage.name_sw" label="Kiswahili" id="s_name_sw" placeholder="e.g. Vifaranga (wiki 0–4)" />
                    <x-ui.input type="number" min="0" wire:model="stage.sort_order" label="Order" id="s_sort" :error="$errors->first('stage.sort_order')" />
                    <div class="md:col-span-3"><x-ui.textarea wire:model="stage.description" label="Description (what happens at this stage, what it eats)" id="s_desc" rows="2"></x-ui.textarea></div>
                    <div class="flex gap-2 md:col-span-3">
                        <x-ui.button type="submit" target="saveStage">{{ $stageId ? 'Save stage' : 'Add stage' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('stageForm', false)">Cancel</x-ui.button>
                    </div>
                </div>
            </form>
        @endif

        @if($stages->isEmpty())
            <p class="rounded-[16px] border border-dashed border-hairline px-4 py-6 text-center text-[13px] text-ink-mute">No growth stages yet. Add the stages farmers buy for, e.g. Starter, Grower, Layer.</p>
        @else
            <ol class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($stages as $i => $s)
                    <li wire:key="stage-{{ $s->id }}" class="flex gap-3 rounded-[18px] border border-hairline bg-canvas p-3 {{ $s->is_active ? '' : 'opacity-50' }}">
                        <div class="relative shrink-0">
                            <label for="stage_photo_{{ $s->id }}" class="flex h-20 w-20 cursor-pointer items-center justify-center overflow-hidden rounded-[14px] bg-canvas-soft hover:ring-2 hover:ring-primary/30" title="{{ $s->imageUrl() ? 'Change photo' : 'Add photo' }}">
                                @if($s->imageUrl())<img src="{{ $s->imageUrl() }}" alt="{{ $s->name }}" class="h-full w-full object-cover">
                                @else<span class="text-center text-[11px] leading-tight text-ink-mute">+ Photo</span>@endif
                            </label>
                            <input type="file" id="stage_photo_{{ $s->id }}" wire:model="stagePhotos.{{ $s->id }}" accept="image/*" class="sr-only">
                            <span class="absolute -left-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[14px] font-semibold text-ink">{{ $s->name }}</p>
                            <p class="text-[12px] text-ink-mute">{{ $s->name_sw ?: '—' }} · {{ $s->products_count }} {{ \Illuminate\Support\Str::plural('product', $s->products_count) }}</p>
                            @if($s->description)<p class="mt-1 line-clamp-2 text-[12px] text-ink-secondary">{{ $s->description }}</p>@endif
                            <span wire:loading wire:target="stagePhotos.{{ $s->id }}" class="text-[11.5px] text-ink-mute">Uploading…</span>
                            @error('stagePhotos.'.$s->id) <p class="text-[12px] text-ruby">{{ $message }}</p> @enderror
                            <div class="mt-1.5 flex flex-wrap gap-x-3 text-[12px]">
                                <button type="button" wire:click="editStage({{ $s->id }})" class="font-semibold text-primary hover:text-primary-deep">Edit</button>
                                @if($s->imageUrl())<button type="button" wire:click="removeStagePhoto({{ $s->id }})" wire:confirm="Remove this stage's photo?" class="text-ink-mute hover:text-ruby">Remove photo</button>@endif
                                <button type="button" wire:click="toggleStage({{ $s->id }})" class="{{ $s->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $s->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Products for this animal --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_2fr]">
        <x-ui.card>
            <h2 class="text-[15px] font-semibold text-ink">What it's sold for</h2>
            @forelse($categories as $name => $total)
                @php($max = $categories->max())
                <div class="mt-3">
                    <div class="flex justify-between text-[12.5px]"><span class="text-ink-secondary">{{ $name }}</span><span class="tnum text-ink-mute">{{ $total }}</span></div>
                    <div class="mt-1 h-1.5 rounded-full bg-canvas-soft"><div class="h-full rounded-full bg-primary/70" style="width: {{ $max ? round(100 * $total / $max) : 0 }}%"></div></div>
                </div>
            @empty
                <p class="mt-3 text-[13px] text-ink-mute">No products yet.</p>
            @endforelse
        </x-ui.card>
        <x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-semibold text-ink">Latest products</h2>
                <a href="{{ route('admin.products.index', ['animalFilter' => $animal->id, 'status' => 'all']) }}" class="text-[12.5px] font-semibold text-primary hover:text-primary-deep">All {{ $stats['products'] }} →</a>
            </div>
            @if($recent->isEmpty())
                <p class="mt-3 text-[13px] text-ink-mute">No products for this animal yet.</p>
            @else
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach($recent as $p)
                        <a href="{{ route('admin.products.index', ['search' => $p->name, 'status' => 'all']) }}" class="flex items-center gap-3 rounded-[12px] border border-hairline px-3 py-2 hover:border-primary/40">
                            @if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="h-10 w-10 rounded object-contain bg-white">@else<span class="h-10 w-10 rounded bg-canvas-soft"></span>@endif
                            <span class="min-w-0 text-[12.5px]">
                                <span class="block truncate font-medium text-ink">{{ $p->name }}</span>
                                <span class="block truncate text-ink-mute">{{ $p->producer?->name ?? $p->brand }} · {{ $p->category?->name }}</span>
                            </span>
                            @if($p->verified_at)<span class="ml-auto text-[12px] font-semibold text-success" title="Verified">✓</span>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
