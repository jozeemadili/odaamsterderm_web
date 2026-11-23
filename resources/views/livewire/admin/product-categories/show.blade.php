<div class="space-y-5">
    <nav class="flex items-center gap-1.5 text-[13px] text-ink-mute">
        <a href="{{ route('admin.product-categories.index') }}" class="text-primary hover:text-primary-deep">Categories</a>
        @if($category->parent)<span>›</span><a href="{{ route('admin.product-categories.show', $category->parent) }}" class="text-primary hover:text-primary-deep">{{ $category->parent->name }}</a>@endif
        <span>›</span><span class="text-ink">{{ $category->name }}</span>
    </nav>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    {{-- Hero --}}
    <section class="overflow-hidden rounded-[24px] border border-hairline bg-canvas">
        <div class="grid md:grid-cols-[300px_1fr]">
            <div class="relative h-56 bg-gradient-to-br from-canvas-cream to-canvas-soft md:h-full md:min-h-[240px]">
                @if($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                @else
                    <span class="flex h-full items-center justify-center text-[88px]" aria-hidden="true">{{ $category->emoji() }}</span>
                @endif
                <div class="absolute inset-x-3 bottom-3 flex flex-wrap gap-2">
                    <label for="category_photo" class="cursor-pointer rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] font-semibold text-ink shadow-sm hover:bg-white">{{ $category->imageUrl() ? 'Change photo' : '+ Add photo' }}</label>
                    <input type="file" id="category_photo" wire:model="photo" accept="image/*" class="sr-only">
                    @if($category->imageUrl())<button type="button" wire:click="removePhoto" wire:confirm="Remove this photo?" class="rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] text-ink-mute shadow-sm hover:text-ruby">Remove</button>@endif
                    <span wire:loading wire:target="photo" class="rounded-pill bg-white/90 px-3 py-1.5 text-[12.5px] text-ink-mute">Uploading…</span>
                </div>
                @error('photo') <p class="absolute left-3 top-3 rounded bg-white px-2 py-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
            </div>
            <div class="p-5 md:p-6">
                @if($editing)
                    <form wire:submit="saveDetails" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-ui.input wire:model="details.name" label="Name" id="d_name" :error="$errors->first('details.name')" />
                        <x-ui.input wire:model="details.name_sw" label="Name (Kiswahili)" id="d_name_sw" :error="$errors->first('details.name_sw')" />
                        <div class="md:col-span-2"><x-ui.textarea wire:model="details.description" label="What belongs here" id="d_desc" rows="3" :error="$errors->first('details.description')"></x-ui.textarea></div>
                        <x-ui.input type="number" min="0" wire:model="details.sort_order" label="Sort order" id="d_sort" :error="$errors->first('details.sort_order')" />
                        <div class="flex items-end gap-2">
                            <x-ui.button type="submit" target="saveDetails">Save</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="$set('editing', false)">Cancel</x-ui.button>
                        </div>
                    </form>
                @else
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[12px] uppercase tracking-wide text-ink-mute">{{ $category->parent ? 'Subcategory of '.$category->parent->name : 'Main category' }} · {{ $category->code }}</p>
                            <h1 class="mt-1 font-display text-[28px] font-bold leading-tight text-ink">{{ $category->name }}</h1>
                            <p class="text-[15px] text-ink-secondary">{{ $category->name_sw ?: 'No Kiswahili name yet' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            <x-ui.button size="sm" variant="secondary" wire:click="$set('editing', true)">Edit details</x-ui.button>
                        </div>
                    </div>
                    <p class="mt-3 max-w-2xl text-[14px] text-ink-secondary">{{ $category->description ?: 'No description yet. Add one so sellers know what belongs here.' }}</p>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        @foreach([['Products', $stats['products'], 'text-ink'], ['Approved', $stats['approved'], 'text-ink'], ['Verified', $stats['verified'], 'text-success'], ['Missing details', $stats['missing'], $stats['missing'] ? 'text-ruby' : 'text-ink-mute'], ['Agents stocking', $stats['sellers'], 'text-ink']] as [$label, $count, $tone])
                            <div class="rounded-[14px] bg-canvas-soft px-3 py-2.5">
                                <span class="block font-display text-[22px] font-bold leading-none tnum {{ $tone }}">{{ $count }}</span>
                                <span class="mt-1 block text-[12px] text-ink-mute">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-[12.5px]">
                        <a href="{{ route('admin.products.index', ['categoryFilter' => $category->id, 'status' => 'all']) }}" class="font-semibold text-primary hover:text-primary-deep">See its products →</a>
                        @if($stats['missing'])<a href="{{ route('admin.products.index', ['categoryFilter' => $category->id, 'status' => 'all', 'quality' => 'missing', 'sort' => 'least_complete']) }}" class="font-semibold text-ruby hover:opacity-80">Fix {{ $stats['missing'] }} with missing details →</a>@endif
                        <button type="button" wire:click="toggleActive" wire:confirm="{{ $category->is_active ? 'Deactivate '.$category->name.'? It will be hidden from new products and marketplace filters.' : 'Reactivate '.$category->name.'?' }}" class="{{ $category->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $category->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Subcategories (main category only) --}}
    @unless($category->parent)
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[17px] font-semibold text-ink">Subcategories of {{ $category->name }}</h2>
                <button type="button" wire:click="$toggle('subForm')" class="text-[13px] font-semibold text-primary hover:text-primary-deep">+ Add subcategory</button>
            </div>
            @if($subForm)
                <form wire:submit="saveSub" class="grid grid-cols-1 gap-3 rounded-[16px] border border-hairline bg-canvas p-4 md:grid-cols-3">
                    <x-ui.input wire:model="sub.name" label="Name" id="sub_name" placeholder="e.g. Calcium supplements" :error="$errors->first('sub.name')" />
                    <x-ui.input wire:model="sub.name_sw" label="Kiswahili" id="sub_name_sw" />
                    <x-ui.input wire:model="sub.description" label="What belongs here" id="sub_desc" />
                    <div><x-ui.button type="submit" target="saveSub">Add</x-ui.button></div>
                </form>
            @endif
            @if($category->children->isEmpty())
                <p class="rounded-[16px] border border-dashed border-hairline px-4 py-6 text-center text-[13px] text-ink-mute">No subcategories yet.</p>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($category->children as $child)
                        @php($req = $subRequirements[$child->id] ?? null)
                        @php($extra = $req ? array_values(array_diff($req['required'], ['Name', 'Producer', 'Category', 'Animals', 'Front photo', 'Pack size'])) : [])
                        <div wire:key="sub-{{ $child->id }}" class="flex gap-3 rounded-[18px] border border-hairline bg-canvas p-3 {{ $child->is_active ? '' : 'opacity-50' }}">
                            <label for="sub_photo_{{ $child->id }}" class="flex h-20 w-20 shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-[14px] bg-canvas-soft hover:ring-2 hover:ring-primary/30" title="{{ $child->imageUrl() ? 'Change photo' : 'Add photo' }}">
                                @if($child->imageUrl())<img src="{{ $child->imageUrl() }}" alt="{{ $child->name }}" class="h-full w-full object-cover">@else<span class="text-center text-[11px] leading-tight text-ink-mute"><span class="block text-[24px]" aria-hidden="true">{{ $child->emoji() }}</span>+ Photo</span>@endif
                            </label>
                            <input type="file" id="sub_photo_{{ $child->id }}" wire:model="subPhotos.{{ $child->id }}" accept="image/*" class="sr-only">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[14px] font-semibold text-ink">{{ $child->name }}</p>
                                <p class="text-[12px] text-ink-mute">{{ $child->name_sw ?: '—' }} · {{ $child->code }} · {{ $child->products_count }} products</p>
                                @if($child->missing_count)<p class="text-[12px] text-ruby">{{ $child->missing_count }} missing details</p>@endif
                                @if($extra)<p class="mt-1 line-clamp-2 text-[11.5px] text-ink-secondary" title="{{ implode(', ', $extra) }}">Needs: {{ implode(', ', $extra) }}</p>@endif
                                <span wire:loading wire:target="subPhotos.{{ $child->id }}" class="text-[11.5px] text-ink-mute">Uploading…</span>
                                @error('subPhotos.'.$child->id) <p class="text-[12px] text-ruby">{{ $message }}</p> @enderror
                                <a href="{{ route('admin.product-categories.show', $child) }}" class="mt-1 inline-block text-[12px] font-semibold text-primary hover:text-primary-deep">View more →</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endunless

    {{-- What a product here must have (subcategory) --}}
    @if($requirements)
        <section class="rounded-[20px] border border-hairline bg-canvas p-5">
            <h2 class="text-[17px] font-semibold text-ink">What a product here must have</h2>
            <p class="mt-1 text-[12.5px] text-ink-mute">A new product in {{ $category->name }} can't be approved until the required details are filled. Recommended details raise its completeness score.</p>
            <div class="mt-3 flex flex-wrap gap-1.5 text-[12.5px]">
                @foreach($requirements['required'] as $label)<span class="rounded-pill bg-ruby/10 px-2.5 py-1 font-semibold text-ruby">• {{ $label }}</span>@endforeach
                @foreach($requirements['when'] as $label)<span class="rounded-pill bg-canvas-cream px-2.5 py-1 text-ink">◐ {{ $label }}</span>@endforeach
                @foreach($requirements['recommended'] as $label)<span class="rounded-pill border border-dashed border-hairline px-2.5 py-1 text-ink-mute">+ {{ $label }}</span>@endforeach
            </div>
        </section>
    @endif

    {{-- Who and what --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach(['For which animals' => $animals, 'Top producers' => $producers] as $title => $rows)
            <x-ui.card>
                <h2 class="text-[15px] font-semibold text-ink">{{ $title }}</h2>
                @forelse($rows as $name => $total)
                    @php($max = $rows->max())
                    <div class="mt-3">
                        <div class="flex justify-between gap-2 text-[12.5px]"><span class="truncate text-ink-secondary">{{ $name }}</span><span class="tnum text-ink-mute">{{ $total }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-canvas-soft"><div class="h-full rounded-full bg-primary/70" style="width: {{ $max ? round(100 * $total / $max) : 0 }}%"></div></div>
                    </div>
                @empty
                    <p class="mt-3 text-[13px] text-ink-mute">No products yet.</p>
                @endforelse
            </x-ui.card>
        @endforeach
        <x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-semibold text-ink">Latest products</h2>
                <a href="{{ route('admin.products.index', ['categoryFilter' => $category->id, 'status' => 'all']) }}" class="text-[12.5px] font-semibold text-primary hover:text-primary-deep">All {{ $stats['products'] }} →</a>
            </div>
            @forelse($recent as $p)
                <a href="{{ route('admin.products.index', ['search' => $p->name, 'status' => 'all']) }}" class="mt-2 flex items-center gap-3 rounded-[12px] border border-hairline px-3 py-2 hover:border-primary/40">
                    @if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="h-9 w-9 rounded object-contain bg-white">@else<span class="h-9 w-9 rounded bg-canvas-soft"></span>@endif
                    <span class="min-w-0 text-[12.5px]">
                        <span class="block truncate font-medium text-ink">{{ $p->name }}</span>
                        <span class="block truncate text-ink-mute">{{ $p->producer?->name ?? $p->brand }}@if($category->parent_id === null) · {{ $p->category?->name }}@endif</span>
                    </span>
                    @if($p->verified_at)<span class="ml-auto text-[12px] font-semibold text-success" title="Verified">✓</span>@elseif($p->missing_required)<span class="ml-auto text-[11px] text-ruby" title="Missing required details">{{ $p->missing_required }} missing</span>@endif
                </a>
            @empty
                <p class="mt-3 text-[13px] text-ink-mute">No products in this category yet.</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
