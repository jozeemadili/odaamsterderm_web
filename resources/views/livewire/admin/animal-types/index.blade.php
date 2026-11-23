<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">The animals farmers buy for. Each group (Poultry, Cattle…) has types under it (Chicken, Dairy cattle…) and growth stages (Starter, Grower…). Filtering the marketplace by a group also finds its types.</p>
        <div class="flex shrink-0 items-center gap-2">
            @include('livewire.admin.partials.excel-button')
            <x-ui.button wire:click="startCreating">+ Add animal</x-ui.button>
        </div>
    </div>

    @include('livewire.admin.partials.excel-panel', ['what' => 'animal types', 'images' => 'images'])

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach([['Animal groups', $stats['groups'], 'text-ink'], ['Types under groups', $stats['types'], 'text-ink'], ['Growth stages', $stats['stages'], 'text-ink'], ['Without a photo', $stats['no_image'], $stats['no_image'] ? 'text-ruby' : 'text-ink-mute']] as [$label, $count, $tone])
            <div class="rounded-[16px] border border-hairline bg-canvas px-4 py-3">
                <span class="block font-display text-[24px] font-bold leading-none tnum {{ $tone }}">{{ $count }}</span>
                <span class="mt-1 block text-[12.5px] text-ink-mute">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    @if($showForm)
        @include('livewire.admin.animal-types.form', ['groups' => $groups])
    @endif

    <div class="max-w-sm">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search animals (English or Kiswahili)…" id="animal_search" />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($groups as $group)
            <article wire:key="group-{{ $group->id }}" class="flex flex-col overflow-hidden rounded-[20px] border border-hairline bg-canvas transition hover:border-primary/30 hover:shadow-sm {{ $group->is_active ? '' : 'opacity-60' }}">
                <a href="{{ route('admin.animal-types.show', $group) }}" class="relative block h-36 bg-gradient-to-br from-canvas-cream to-canvas-soft">
                    @if($group->imageUrl())
                        <img src="{{ $group->imageUrl() }}" alt="{{ $group->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full items-center justify-center text-[64px]" aria-hidden="true">{{ $group->emoji() }}</span>
                        <span class="absolute bottom-2 right-3 rounded-pill bg-white/80 px-2 py-0.5 text-[11px] text-ink-mute">No photo yet</span>
                    @endif
                    @unless($group->is_active)<span class="absolute left-3 top-3 rounded-pill bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-ink-mute">Inactive</span>@endunless
                </a>
                <div class="flex flex-1 flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="truncate text-[16px] font-semibold text-ink" title="{{ $group->name }}">{{ $group->shortName() }}</h3>
                            <p class="text-[12.5px] text-ink-mute">{{ $group->name_sw ?: '—' }} · {{ $group->code }}</p>
                        </div>
                        <span class="shrink-0 rounded-pill bg-canvas-soft px-2.5 py-1 text-[12px] font-semibold text-ink tnum">{{ $group->tagged_products_count }} {{ \Illuminate\Support\Str::plural('product', $group->tagged_products_count) }}</span>
                    </div>

                    @if($group->children->isNotEmpty())
                        <div class="mt-3">
                            <p class="text-[11px] uppercase tracking-wide text-ink-mute">Types</p>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach($group->children as $child)
                                    <a href="{{ route('admin.animal-types.show', $child) }}" class="inline-flex items-center gap-1.5 rounded-pill border border-hairline bg-canvas py-0.5 pl-0.5 pr-2.5 text-[12px] text-ink-secondary hover:border-primary/40 {{ $child->is_active ? '' : 'opacity-50' }}">
                                        @if($child->imageUrl())
                                            <img src="{{ $child->imageUrl() }}" alt="" class="h-5 w-5 rounded-full object-cover">
                                        @else
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-canvas-soft text-[11px]" aria-hidden="true">{{ $child->emoji() }}</span>
                                        @endif
                                        {{ $child->name }} <span class="text-ink-mute tnum">{{ $child->tagged_products_count }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($group->lifeStages->isNotEmpty())
                        <div class="mt-3">
                            <p class="text-[11px] uppercase tracking-wide text-ink-mute">Growth stages</p>
                            <div class="mt-1.5 flex -space-x-2">
                                @foreach($group->lifeStages->take(6) as $stage)
                                    <span title="{{ $stage->name }}" class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border-2 border-canvas bg-canvas-soft text-[10px] font-semibold text-ink-mute">
                                        @if($stage->imageUrl())<img src="{{ $stage->imageUrl() }}" alt="" class="h-full w-full object-cover">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($stage->name, 0, 2)) }}@endif
                                    </span>
                                @endforeach
                                <span class="ml-3 self-center pl-2 text-[12px] text-ink-mute">{{ $group->life_stages_count }} {{ \Illuminate\Support\Str::plural('stage', $group->life_stages_count) }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="mt-auto flex items-center gap-3 border-t border-hairline pt-3 mt-4 text-[12.5px]">
                        <a href="{{ route('admin.animal-types.show', $group) }}" class="inline-flex items-center gap-1 rounded-pill bg-primary px-3.5 py-1.5 font-semibold text-white hover:bg-primary-deep">View more →</a>
                        <button wire:click="startEditing({{ $group->id }})" class="text-ink-mute hover:text-ink">Edit</button>
                        <button wire:click="startCreating({{ $group->id }})" class="text-ink-mute hover:text-ink">+ Type</button>
                        <button wire:click="toggleActive({{ $group->id }})" wire:confirm="{{ $group->is_active ? 'Deactivate '.$group->name.'?' : 'Reactivate '.$group->name.'?' }}" class="ml-auto {{ $group->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $group->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-[18px] border border-dashed border-hairline px-5 py-12 text-center text-[13px] text-ink-mute">No animals match.</div>
        @endforelse
    </div>
</div>
