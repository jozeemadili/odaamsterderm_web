<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">What each product is: main categories (Feed, Medicines…) with subcategories under them (Complete feed, Dewormers…). Animal, growth stage and pack size are set on the product, so any category works for any animal.</p>
        <div class="flex shrink-0 items-center gap-2">
            @include('livewire.admin.partials.excel-button')
            <x-ui.button wire:click="startCreating">+ Add category</x-ui.button>
        </div>
    </div>

    @include('livewire.admin.partials.excel-panel', ['what' => 'categories', 'images' => 'images'])

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach([['Main categories', $stats['main'], 'text-ink'], ['Subcategories', $stats['sub'], 'text-ink'], ['Subcategories with no products', $stats['empty'], 'text-ink-mute'], ['Without a photo', $stats['no_image'], $stats['no_image'] ? 'text-ruby' : 'text-ink-mute']] as [$label, $count, $tone])
            <div class="rounded-[16px] border border-hairline bg-canvas px-4 py-3">
                <span class="block font-display text-[24px] font-bold leading-none tnum {{ $tone }}">{{ $count }}</span>
                <span class="mt-1 block text-[12.5px] text-ink-mute">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    @if($showForm)
        <x-ui.card>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-[15px] font-semibold text-ink">{{ $editingId ? 'Edit category' : ($parent_id ? 'New subcategory under '.($categories->firstWhere('id', (int) $parent_id)?->name ?? '') : 'New main category') }}</h2>
                <button type="button" wire:click="$set('showForm', false)" class="text-[13px] text-ink-mute hover:text-ink">Close</button>
            </div>
            <form wire:submit="save" class="grid grid-cols-1 gap-5 md:grid-cols-[160px_1fr]">
                <div>
                    @php($current = $editingId ? \App\Models\ProductCategory::find($editingId) : null)
                    <label for="category_image" class="flex h-36 w-36 cursor-pointer items-center justify-center overflow-hidden rounded-[20px] border border-dashed border-hairline bg-canvas-soft hover:border-primary/50">
                        @if($image && method_exists($image, 'temporaryUrl'))<img src="{{ $image->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                        @elseif($current?->imageUrl())<img src="{{ $current->imageUrl() }}" alt="" class="h-full w-full object-cover">
                        @else<span class="px-3 text-center text-[12px] text-ink-mute"><span class="block text-[34px]" aria-hidden="true">{{ $current?->emoji() ?? '📦' }}</span>Add a photo</span>@endif
                    </label>
                    <input type="file" wire:model="image" id="category_image" accept="image/*" class="sr-only">
                    <span wire:loading wire:target="image" class="mt-1 block text-[11.5px] text-ink-mute">Uploading…</span>
                    @error('image') <p class="mt-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-ui.input wire:model="name" label="Name" id="category_name" placeholder="e.g. Feed, Dewormers" :error="$errors->first('name')" />
                    <x-ui.input wire:model="name_sw" label="Name (Kiswahili)" id="category_name_sw" placeholder="e.g. Dawa za minyoo" :error="$errors->first('name_sw')" />
                    <x-ui.select wire:model.live="parent_id" label="Main category" id="category_parent" :error="$errors->first('parent_id')">
                        <option value="">— This is a main category —</option>
                        @foreach($categories as $category)
                            @if($category->id !== $editingId)<option value="{{ $category->id }}">{{ $category->name }}</option>@endif
                        @endforeach
                    </x-ui.select>
                    <div class="md:col-span-2">
                        <x-ui.textarea wire:model="description" label="What belongs here (shown to sellers choosing a category)" id="category_description" rows="2" :error="$errors->first('description')"></x-ui.textarea>
                    </div>
                    <x-ui.input type="number" min="0" wire:model="sort_order" label="Sort order" id="category_sort" :error="$errors->first('sort_order')" />
                    <div class="flex gap-2 md:col-span-3">
                        <x-ui.button type="submit" target="save">{{ $editingId ? 'Save changes' : 'Add' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="max-w-sm">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search categories (English, Kiswahili or code)…" id="category_search" />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($categories as $main)
            @php($total = $main->products_count + $main->children->sum('products_count'))
            <article wire:key="cat-{{ $main->id }}" class="flex flex-col overflow-hidden rounded-[20px] border border-hairline bg-canvas transition hover:border-primary/30 hover:shadow-sm {{ $main->is_active ? '' : 'opacity-60' }}">
                <a href="{{ route('admin.product-categories.show', $main) }}" class="relative block h-32 bg-gradient-to-br from-canvas-cream to-canvas-soft">
                    @if($main->imageUrl())
                        <img src="{{ $main->imageUrl() }}" alt="{{ $main->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full items-center justify-center text-[56px]" aria-hidden="true">{{ $main->emoji() }}</span>
                        <span class="absolute bottom-2 right-3 rounded-pill bg-white/80 px-2 py-0.5 text-[11px] text-ink-mute">No photo yet</span>
                    @endif
                    @unless($main->is_active)<span class="absolute left-3 top-3 rounded-pill bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-ink-mute">Inactive</span>@endunless
                </a>
                <div class="flex flex-1 flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="truncate text-[16px] font-semibold text-ink">{{ $main->name }}</h3>
                            <p class="text-[12.5px] text-ink-mute">{{ $main->name_sw ?: '—' }} · {{ $main->code }}</p>
                        </div>
                        <span class="shrink-0 rounded-pill bg-canvas-soft px-2.5 py-1 text-[12px] font-semibold text-ink tnum">{{ $total }} {{ \Illuminate\Support\Str::plural('product', $total) }}</span>
                    </div>
                    @if($main->description)<p class="mt-2 line-clamp-2 text-[12.5px] text-ink-secondary">{{ $main->description }}</p>@endif

                    @if($main->children->isNotEmpty())
                        <div class="mt-3">
                            <p class="text-[11px] uppercase tracking-wide text-ink-mute">{{ $main->children->count() }} subcategories</p>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach($main->children as $child)
                                    <a href="{{ route('admin.product-categories.show', $child) }}" class="inline-flex items-center gap-1.5 rounded-pill border border-hairline bg-canvas py-0.5 pl-0.5 pr-2.5 text-[12px] text-ink-secondary hover:border-primary/40 {{ $child->is_active ? '' : 'opacity-50' }}">
                                        @if($child->imageUrl())<img src="{{ $child->imageUrl() }}" alt="" class="h-5 w-5 rounded-full object-cover">@else<span class="flex h-5 w-5 items-center justify-center rounded-full bg-canvas-soft text-[10px]" aria-hidden="true">{{ $child->emoji() }}</span>@endif
                                        {{ $child->name }} <span class="text-ink-mute tnum">{{ $child->products_count }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-auto flex items-center gap-3 border-t border-hairline pt-3 mt-4 text-[12.5px]">
                        <a href="{{ route('admin.product-categories.show', $main) }}" class="inline-flex items-center gap-1 rounded-pill bg-primary px-3.5 py-1.5 font-semibold text-white hover:bg-primary-deep">View more →</a>
                        <button wire:click="startEditing({{ $main->id }})" class="text-ink-mute hover:text-ink">Edit</button>
                        <button wire:click="startCreating({{ $main->id }})" class="text-ink-mute hover:text-ink">+ Subcategory</button>
                        <button wire:click="toggleActive({{ $main->id }})" wire:confirm="{{ $main->is_active ? 'Deactivate '.$main->name.'?' : 'Reactivate '.$main->name.'?' }}" class="ml-auto {{ $main->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $main->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-[18px] border border-dashed border-hairline px-5 py-12 text-center text-[13px] text-ink-mute">No categories match.</div>
        @endforelse
    </div>
</div>
