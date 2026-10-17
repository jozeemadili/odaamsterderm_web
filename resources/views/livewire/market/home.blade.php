<div class="space-y-5">
    @unless($from)
        <div class="rounded-xl bg-primary-subtle/40 border border-primary/20 px-4 py-3 text-[14px] text-ink flex flex-wrap items-center justify-between gap-3">
            <span>Set your location to see the nearest stores and how far each one is.</span>
            <button type="button" x-data x-on:click="document.querySelector('header [wire\\:click=openPicker]')?.click()" class="rounded-pill bg-primary text-ink font-medium px-3.5 py-1.5 text-[13px] hover:bg-primary-deep">Set location</button>
        </div>
    @endunless

    {{-- Animal type chips --}}
    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        <button wire:click="chooseAnimal(null)" class="shrink-0 text-[13px] px-3.5 py-1.5 rounded-pill border {{ $animal === '' ? 'bg-brand-dark text-white border-brand-dark' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">All animals</button>
        @foreach($animalGroups as $group)
            <button wire:click="chooseAnimal({{ $group->id }})" class="shrink-0 text-[13px] px-3.5 py-1.5 rounded-pill border {{ $animal === (string) $group->id ? 'bg-brand-dark text-white border-brand-dark' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $group->name }}</button>
            @if($animal !== '' && ($animal === (string) $group->id || $group->children->contains('id', (int) $animal)))
                @foreach($group->children as $child)
                    <button wire:click="chooseAnimal({{ $child->id }})" class="shrink-0 text-[13px] px-3.5 py-1.5 rounded-pill border {{ $animal === (string) $child->id ? 'bg-primary text-ink border-primary' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $child->name }}</button>
                @endforeach
            @endif
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-5">
        {{-- Filters --}}
        <aside class="space-y-3">
            <x-ui.card padding="p-4">
                <div class="space-y-3">
                    <x-ui.select wire:model.live="sort" label="Sort by" id="mk_sort">
                        @if($askAi)<option value="relevance">Best match (AI)</option>@endif
                        @foreach(\App\Services\MarketplaceSearch::SORTS as $key => $label)
                            <option value="{{ $key }}" @disabled($key === 'nearest' && ! $from)>{{ $label }}{{ $key === 'nearest' && ! $from ? ' (set location)' : '' }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select wire:model.live="category" label="Category" id="mk_category">
                        <option value="">All categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @foreach($cat->children as $child)
                                <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </x-ui.select>
                    @if(count($brands))
                        <x-ui.select wire:model.live="brand" label="Brand" id="mk_brand">
                            <option value="">All brands</option>
                            @foreach($brands as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
                        </x-ui.select>
                    @endif
                    <x-ui.select wire:model.live="seller" label="Seller" id="mk_seller">
                        <option value="">All sellers</option>
                        @foreach($sellers as $s)<option value="{{ $s->id }}">{{ $s->business_name }}</option>@endforeach
                    </x-ui.select>
                    <div>
                        <p class="text-[13px] text-ink-mute mb-1.5">Price (TZS)</p>
                        <div class="flex gap-2">
                            <input type="number" min="0" wire:model.live.debounce.500ms="minPrice" placeholder="Min" class="w-full rounded-sm border border-hairline-input px-2.5 py-1.5 text-[14px]">
                            <input type="number" min="0" wire:model.live.debounce.500ms="maxPrice" placeholder="Max" class="w-full rounded-sm border border-hairline-input px-2.5 py-1.5 text-[14px]">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-ink-secondary">
                        <input type="checkbox" wire:model.live="inStock" class="rounded border-hairline-input text-primary focus:ring-primary"> Available now only
                    </label>
                    @if($hasFilters)
                        <button wire:click="clearFilters" class="text-[12px] text-ink-mute hover:text-ruby">Clear filters</button>
                    @endif
                </div>
            </x-ui.card>
        </aside>

        <section class="space-y-5">
            @if(\App\Services\Ai\AiOrderAssistant::enabled())
                <div class="rounded-xl bg-brand-dark/5 border border-brand-dark/10 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[15px] font-medium text-ink">Not sure what to buy?</p>
                        <p class="text-[13px] text-ink-mute">Tell our AI what your animals need and it builds the order from stores near you.</p>
                    </div>
                    <livewire:market.ai-assistant />
                </div>
            @endif

            @if($askAi)
                <div class="rounded-xl border border-primary/30 bg-primary-subtle/30 p-4 space-y-2" @if($aiPending) wire:init="runAi" @endif>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[12px] uppercase tracking-wide font-medium text-primary-deep">✨ Ask AI</p>
                        <button type="button" wire:click="clearAi" class="text-[12px] text-ink-mute hover:text-ruby">Clear AI search</button>
                    </div>
                    <p class="text-[15px] text-ink">“{{ $ask }}”</p>
                    @if($aiPending)
                        <p class="text-[14px] text-ink-secondary flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full border-2 border-primary border-t-transparent animate-spin"></span>
                            Finding the right products in live stock…
                        </p>
                    @elseif($aiStatus === 'sign_in')
                        <p class="text-[14px] text-ink-secondary"><a href="{{ route('login') }}" class="text-primary-deep font-medium underline">Sign in</a> to use Ask AI, or use the normal search.</p>
                    @elseif($aiStatus === 'limited')
                        <p class="text-[14px] text-ink-secondary">Ask AI is busy right now. Showing normal search results instead — please try again in a minute.</p>
                    @elseif($aiStatus === 'unavailable')
                        <p class="text-[14px] text-ink-secondary">Ask AI is not available right now. Showing normal search results instead.</p>
                    @elseif($aiStatus === 'refused')
                        <p class="text-[14px] text-ink-secondary">Ask AI can't help with that request. Try describing the animal product you need.</p>
                    @else
                        <p class="text-[14px] text-ink-secondary whitespace-pre-line">{{ $ai['summary'] }}</p>
                    @endif
                    @if(! $aiPending && in_array($aiStatus, ['ok', 'off_topic'], true))
                        <p class="text-[11px] text-ink-mute">AI suggestions can be wrong — check each product before you buy. Prices, stock and distances are live from the stores.</p>
                    @endif
                </div>
            @endif

            <div class="flex items-baseline justify-between">
                <h1 class="text-[22px] font-light tracking-tight">{{ $askAi ? 'AI picks for you' : ($q !== '' ? 'Results for “'.$q.'”' : 'Animal products') }}</h1>
                <span class="text-[13px] text-ink-mute">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" wire:loading.class="opacity-60">
                @forelse($products as $product)
                    <a href="{{ route('market.product', $product->slug) }}" class="block group">
                        <x-ui.card padding="p-0" class="h-full overflow-hidden group-hover:border-primary/40 transition">
                            <div class="aspect-[16/9] bg-canvas-soft flex items-center justify-center overflow-hidden">
                                @if($product->image())
                                    <img src="{{ $product->image()->getUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <x-icon.inventory class="h-10 w-10 text-ink-mute/50" />
                                @endif
                            </div>
                            <div class="p-4">
                                <p class="text-[11px] uppercase tracking-wide text-ink-mute">{{ $product->animalType?->name }} · {{ $product->category?->fullName() }}</p>
                                <h2 class="text-[15px] font-medium text-ink mt-1">{{ $product->displayName() }}</h2>
                                @if($product->brand)<p class="text-[12px] text-ink-mute">{{ $product->brand }}</p>@endif
                                <div class="mt-3 flex items-end justify-between gap-2">
                                    <div>
                                        @if($product->min_price !== null)
                                            <p class="text-[11px] text-ink-mute">from</p>
                                            <p class="text-[18px] font-medium tnum">TZS {{ number_format($product->min_price) }}</p>
                                        @else
                                            <p class="text-[13px] text-ruby font-medium">Out of stock</p>
                                        @endif
                                    </div>
                                    <div class="text-right text-[12px] text-ink-mute">
                                        <p>{{ $product->sellers_count }} {{ \Illuminate\Support\Str::plural('seller', $product->sellers_count) }}</p>
                                        @if($product->nearest_km !== null)
                                            <p class="text-primary-deep font-medium">Nearest {{ \App\Support\GeoPoint::formatKm($product->nearest_km) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </x-ui.card>
                    </a>
                @empty
                    @if($aiPending)
                        @foreach(range(1, 3) as $i)
                            <div class="h-64 rounded-xl bg-canvas-soft animate-pulse"></div>
                        @endforeach
                    @else
                        <div class="col-span-full rounded-xl border border-dashed border-hairline bg-canvas p-10 text-center text-ink-mute">
                            No products match. Try another search or clear the filters.
                        </div>
                    @endif
                @endforelse
            </div>

            {{ $products->links() }}

            @if($stores->isNotEmpty())
                <div>
                    <h2 class="text-[17px] font-light tracking-tight mb-3">{{ $from ? 'Stores near you' : 'Stores' }}</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($stores as $store)
                            <a href="{{ route('market.store', $store) }}" class="block">
                                <x-ui.card padding="p-4" class="h-full hover:border-primary/40 transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-[15px] font-medium">{{ $store->name }}</p>
                                            <p class="text-[12px] text-ink-mute">{{ $store->merchant->business_name }}</p>
                                        </div>
                                        @if($store->distance_km !== null)
                                            <x-ui.badge tone="primary">{{ \App\Support\GeoPoint::formatKm($store->distance_km) }}</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="text-[12px] text-ink-mute mt-2">{{ collect([$store->address, $store->city])->filter()->join(', ') }}</p>
                                    <p class="text-[12px] text-primary mt-2">{{ $store->offers_count }} {{ \Illuminate\Support\Str::plural('product', $store->offers_count) }} available &rarr;</p>
                                </x-ui.card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
