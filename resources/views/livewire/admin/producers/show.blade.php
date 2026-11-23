<div class="space-y-5">
    <nav class="flex items-center gap-1.5 text-[13px] text-ink-mute">
        <a href="{{ route('admin.producers.index') }}" class="text-primary hover:text-primary-deep">Producers</a>
        <span>›</span><span class="text-ink">{{ $producer->name }}</span>
    </nav>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    {{-- Hero --}}
    <section class="overflow-hidden rounded-[24px] border border-hairline bg-canvas">
        <div class="grid md:grid-cols-[260px_1fr]">
            <div class="relative flex h-56 items-center justify-center bg-white md:h-full md:min-h-[240px] md:border-r md:border-hairline">
                @if($producer->logoUrl())
                    <img src="{{ $producer->logoUrl() }}" alt="{{ $producer->name }} logo" class="max-h-40 max-w-[200px] object-contain">
                @else
                    <span class="flex h-28 w-28 items-center justify-center rounded-[24px] bg-canvas-soft font-display text-[36px] font-bold text-primary">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($producer->name, 0, 2)) }}</span>
                @endif
                <div class="absolute inset-x-3 bottom-3 flex flex-wrap justify-center gap-2">
                    <label for="producer_logo" class="cursor-pointer rounded-pill border border-hairline bg-white px-3 py-1.5 text-[12.5px] font-semibold text-ink shadow-sm hover:border-primary/40">{{ $producer->logoUrl() ? 'Change logo' : '+ Add logo' }}</label>
                    <input type="file" id="producer_logo" wire:model="logo" accept="image/*" class="sr-only">
                    @if($producer->logoUrl())<button type="button" wire:click="removeLogo" wire:confirm="Remove {{ $producer->name }}'s logo?" class="rounded-pill border border-hairline bg-white px-3 py-1.5 text-[12.5px] text-ink-mute shadow-sm hover:text-ruby">Remove</button>@endif
                    <span wire:loading wire:target="logo" class="rounded-pill bg-white px-3 py-1.5 text-[12.5px] text-ink-mute">Uploading…</span>
                </div>
                @error('logo') <p class="absolute left-3 top-3 rounded bg-white px-2 py-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
            </div>
            <div class="p-5 md:p-6">
                @if($editing)
                    <form wire:submit="saveDetails" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="md:col-span-2"><x-ui.input wire:model="details.name" label="Name" id="d_name" :error="$errors->first('details.name')" /></div>
                        <x-ui.input wire:model="details.country" label="Country" id="d_country" />
                        <div class="md:col-span-2"><x-ui.input wire:model="details.role" label="What they make" id="d_role" /></div>
                        <x-ui.select wire:model="details.deals_in" label="Hatchery (day-old chicks)" id="d_deals" :error="$errors->first('details.deals_in')">
                            <option value="">Not a hatchery</option>
                            @foreach(\App\Models\Producer::DEALS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                        </x-ui.select>
                        <x-ui.input wire:model="details.website" label="Website" id="d_web" placeholder="https://…" :error="$errors->first('details.website')" />
                        <x-ui.input wire:model="details.phone" label="Phone" id="d_phone" />
                        <x-ui.input wire:model="details.location" label="Location" id="d_loc" />
                        <div class="md:col-span-3"><x-ui.textarea wire:model="details.description" label="About (shown to customers)" id="d_desc" rows="3"></x-ui.textarea></div>
                        <div class="flex gap-2 md:col-span-3">
                            <x-ui.button type="submit" target="saveDetails">Save</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="$set('editing', false)">Cancel</x-ui.button>
                        </div>
                    </form>
                @else
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[12px] uppercase tracking-wide text-ink-mute">Producer · {{ $producer->code ?? '—' }}</p>
                            <h1 class="mt-1 font-display text-[28px] font-bold leading-tight text-ink">{{ $producer->name }}</h1>
                            <p class="text-[14px] text-ink-secondary">{{ $producer->role ?: 'What they make is not set' }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$producer->is_active ? 'success' : 'neutral'">{{ $producer->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            @unless($producer->on_marketplace)<x-ui.badge tone="warning">Hidden on marketplace</x-ui.badge>@endunless
                            @if($producer->deals_in)<x-ui.badge tone="neutral">Hatchery · {{ \App\Models\Producer::DEALS[$producer->deals_in] ?? $producer->deals_in }}</x-ui.badge>@endif
                            <x-ui.button size="sm" variant="secondary" wire:click="$set('editing', true)">Edit details</x-ui.button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-ink-secondary">
                        @if($producer->location || $producer->country)<span>📍 {{ collect([$producer->location, $producer->country])->filter()->unique()->join(', ') }}</span>@endif
                        @if($producer->phone)<a href="tel:{{ $producer->phone }}" class="hover:text-primary">📞 {{ $producer->phone }}</a>@endif
                        @if($producer->website)<a href="{{ $producer->website }}" target="_blank" rel="noopener" class="hover:text-primary">🌐 {{ parse_url($producer->website, PHP_URL_HOST) ?: $producer->website }}</a>@endif
                    </div>
                    <p class="mt-3 max-w-2xl text-[14px] text-ink-secondary">{{ $producer->description ?: 'No description yet. Customers see this under the product.' }}</p>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach([['Products', $stats['products'], 'text-ink'], ['Approved', $stats['approved'], 'text-ink'], ['Verified', $stats['verified'], 'text-success'], ['Missing details', $stats['missing'], $stats['missing'] ? 'text-ruby' : 'text-ink-mute'], ['Avg. complete', $stats['average'].'%', 'text-ink'], ['Agents stocking', $stats['sellers'], 'text-ink']] as [$label, $count, $tone])
                            <div class="rounded-[14px] bg-canvas-soft px-3 py-2.5">
                                <span class="block font-display text-[22px] font-bold leading-none tnum {{ $tone }}">{{ $count }}</span>
                                <span class="mt-1 block text-[12px] text-ink-mute">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-[12.5px]">
                        <a href="{{ route('admin.products.index', ['search' => $producer->name, 'status' => 'all']) }}" class="font-semibold text-primary hover:text-primary-deep">Open in the catalogue →</a>
                        <button type="button" wire:click="toggleMarketplace" class="text-ink-mute hover:text-ink">{{ $producer->on_marketplace ? 'Hide on marketplace' : 'Show on marketplace' }}</button>
                        <button type="button" wire:click="toggleActive" wire:confirm="{{ $producer->is_active ? 'Deactivate '.$producer->name.'? It can no longer be chosen for new products.' : 'Reactivate '.$producer->name.'?' }}" class="{{ $producer->is_active ? 'text-ink-mute hover:text-ruby' : 'text-primary' }}">{{ $producer->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Hatchery --}}
    @if($hatchery)
        <section class="rounded-[20px] border border-hairline bg-canvas-cream/60 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[17px] font-semibold text-ink">🐣 Day-old chicks</h2>
                <a href="{{ route('admin.chicks.index') }}" class="text-[12.5px] font-semibold text-primary hover:text-primary-deep">Day-old chicks admin →</a>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3 text-[13px]">
                <div><p class="text-ink-mute">Chick types</p><p class="mt-0.5 font-medium text-ink">{{ $hatchery['types']->join(', ') ?: 'Not set' }}</p></div>
                <div><p class="text-ink-mute">Open order days</p><p class="mt-0.5 font-medium text-ink">{{ $hatchery['upcoming'] }}@if($hatchery['next']) · next {{ \Illuminate\Support\Carbon::parse($hatchery['next'])->format('D j M') }}@endif</p></div>
                <div><p class="text-ink-mute">Agents with a contract</p><p class="mt-0.5 font-medium text-ink">{{ $hatchery['contracts']->pluck('merchant.business_name')->filter()->unique()->join(', ') ?: 'None yet' }}</p></div>
            </div>
        </section>
    @endif

    {{-- What they make, who stocks it --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach(['By category' => $categories, 'For which animals' => $animals, 'Agents stocking (products)' => $agents] as $title => $rows)
            <x-ui.card>
                <h2 class="text-[15px] font-semibold text-ink">{{ $title }}</h2>
                @forelse($rows as $name => $total)
                    @php($max = $rows->max())
                    <div class="mt-3">
                        <div class="flex justify-between gap-2 text-[12.5px]"><span class="truncate text-ink-secondary">{{ $name }}</span><span class="tnum text-ink-mute">{{ $total }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-canvas-soft"><div class="h-full rounded-full bg-primary/70" style="width: {{ $max ? round(100 * $total / $max) : 0 }}%"></div></div>
                    </div>
                @empty
                    <p class="mt-3 text-[13px] text-ink-mute">Nothing yet.</p>
                @endforelse
            </x-ui.card>
        @endforeach
    </div>

    {{-- Products --}}
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[17px] font-semibold text-ink">Products by {{ $producer->name }}</h2>
            <div class="flex gap-1.5 text-[12.5px]">
                @foreach(['' => 'All', 'missing' => 'Missing details', 'verified' => 'Verified'] as $key => $label)
                    <button type="button" wire:click="$set('products', '{{ $key }}')" class="rounded-pill border px-3 py-1 {{ $products === $key ? 'border-primary bg-primary text-white' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if($list->isEmpty())
            <p class="rounded-[16px] border border-dashed border-hairline px-4 py-8 text-center text-[13px] text-ink-mute">No products here.</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($list as $p)
                    <a href="{{ route('admin.products.index', ['search' => $p->name, 'status' => 'all']) }}" wire:key="p-{{ $p->id }}" class="flex flex-col overflow-hidden rounded-[16px] border border-hairline bg-canvas hover:border-primary/40">
                        <span class="flex h-32 items-center justify-center bg-white">
                            @if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="max-h-28 max-w-full object-contain">@else<span class="text-[12px] text-ink-mute">No photo</span>@endif
                        </span>
                        <span class="flex flex-1 flex-col p-3">
                            <span class="line-clamp-2 text-[13px] font-semibold text-ink">{{ $p->name }}</span>
                            <span class="text-[12px] text-ink-mute">{{ $p->category?->name }} · {{ $p->variants->pluck('name')->join(', ') ?: $p->pack_size }}</span>
                            <span class="mt-auto flex items-center gap-1.5 pt-2">
                                <span class="h-1.5 w-12 overflow-hidden rounded-full bg-canvas-soft"><span class="block h-full {{ $p->missing_required ? 'bg-ruby' : 'bg-success' }}" style="width: {{ $p->completeness }}%"></span></span>
                                <span class="text-[11px] tnum {{ $p->missing_required ? 'text-ruby' : 'text-ink-mute' }}">{{ $p->completeness }}%</span>
                                @if($p->verified_at)<span class="text-[11px] font-semibold text-success">✓ Verified</span>@endif
                                @unless($p->isApproved())<span class="text-[11px] text-ink-mute">{{ ucfirst($p->status) }}</span>@endunless
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
            {{ $list->links() }}
        @endif
    </section>
</div>
