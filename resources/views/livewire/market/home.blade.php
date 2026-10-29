<div class="space-y-6">
    @unless($hasFilters)
        <section class="relative overflow-hidden rounded-2xl bg-brand-dark text-white">
            <div class="absolute inset-0 opacity-30" style="background: radial-gradient(60% 80% at 85% 10%, var(--color-primary) 0%, transparent 60%), radial-gradient(50% 60% at 0% 100%, var(--color-shadow-blue) 0%, transparent 70%);"></div>
            <div class="relative px-6 py-8 sm:px-10 sm:py-12 grid gap-8 lg:grid-cols-[1.4fr_1fr] items-center">
                <div>
                    <p class="text-[12px] uppercase tracking-[0.18em] text-primary-soft font-medium">Animal products marketplace</p>
                    <h1 class="mt-3 text-[28px] sm:text-[40px] leading-[1.1] font-light tracking-tight">Everything your animals need, from <span class="text-primary-soft font-normal">trusted sellers</span> near you.</h1>
                    <p class="mt-3 text-[15px] text-white/70 max-w-xl">Compare prices on feeds, vaccines, medicines and equipment for poultry, cattle, goats, pigs and fish — from approved agrovets across Tanzania.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#products" class="rounded-pill bg-primary text-ink font-medium px-5 py-2.5 text-[14px] hover:bg-primary-soft transition">Browse products</a>
                        @unless($from)
                            <button type="button" x-data x-on:click="document.querySelector('header [wire\\:click=openPicker]')?.click()" class="rounded-pill border border-white/25 text-white px-5 py-2.5 text-[14px] hover:bg-white/10 transition">Find stores near me</button>
                        @endunless
                    </div>
                </div>
                <ul class="hidden sm:grid gap-3 text-[14px]">
                    @foreach([
                        ['Approved sellers only', 'Every store is checked before it can sell.'],
                        ['Compare before you buy', 'See every store\'s price for the same product.'],
                        ['Closest stores first', 'Set your location to see how far each one is.'],
                    ] as [$title, $text])
                        <li class="flex gap-3 rounded-xl bg-white/5 border border-white/10 px-4 py-3">
                            <span class="mt-0.5 h-5 w-5 shrink-0 rounded-full bg-primary/20 text-primary-soft flex items-center justify-center">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            </span>
                            <span><span class="block font-medium text-white">{{ $title }}</span><span class="text-white/60 text-[13px]">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endunless

    {{-- Animal type chips --}}
    <div id="products" class="scroll-mt-4 flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 [scrollbar-width:none]">
        <button wire:click="chooseAnimal(null)" class="shrink-0 text-[13px] px-4 py-2 rounded-pill border transition {{ $animal === '' ? 'bg-brand-dark text-white border-brand-dark' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">All animals</button>
        @foreach($animalGroups as $group)
            <button wire:click="chooseAnimal({{ $group->id }})" class="shrink-0 text-[13px] px-4 py-2 rounded-pill border transition {{ $animal === (string) $group->id ? 'bg-brand-dark text-white border-brand-dark' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $group->name }}</button>
            @if($animal !== '' && ($animal === (string) $group->id || $group->children->contains('id', (int) $animal)))
                @foreach($group->children as $child)
                    <button wire:click="chooseAnimal({{ $child->id }})" class="shrink-0 text-[13px] px-4 py-2 rounded-pill border transition {{ $animal === (string) $child->id ? 'bg-primary text-ink border-primary' : 'bg-canvas border-hairline text-ink-secondary hover:border-primary/40' }}">{{ $child->name }}</button>
                @endforeach
            @endif
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-5">
        {{-- Filters --}}
        <aside class="space-y-3" x-data="{ open: false }">
            <button type="button" x-on:click="open = !open" class="lg:hidden w-full flex items-center justify-between rounded-xl border border-hairline bg-canvas px-4 py-2.5 text-[14px] text-ink">
                <span>Filters &amp; sort @if($hasFilters)<span class="ml-1 rounded-pill bg-primary/20 text-primary-deep text-[11px] px-2 py-0.5">on</span>@endif</span>
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 transition" :class="open && 'rotate-180'"><path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </button>
            <x-ui.card padding="p-4" class="hidden lg:block lg:sticky lg:top-4" x-bind:class="open && '!block'">
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
                        <x-ui.select wire:model.live="brand" label="Producer / brand" id="mk_brand">
                            <option value="">All producers</option>
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
                <h2 class="text-[22px] font-light tracking-tight">{{ $askAi ? 'AI picks for you' : ($q !== '' ? 'Results for “'.$q.'”' : 'All products') }}</h2>
                <span class="text-[13px] text-ink-mute">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
            </div>

            <div class="grid grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4" wire:loading.class="opacity-60">
                @forelse($products as $product)
                    <article class="group relative flex flex-col overflow-hidden rounded-xl bg-canvas border border-hairline hover:border-primary/40 hover:shadow-[0_8px_24px_rgba(1,22,47,0.08)] transition">
                        <x-market.photo-carousel :photos="$product->imageUrls()" :alt="$product->displayName()" :href="route('market.product', $product->slug)" class="aspect-square" />
                        <a href="{{ route('market.product', $product->slug) }}" class="flex flex-1 flex-col p-4">
                            <p class="text-[11px] uppercase tracking-wide text-ink-mute truncate">{{ collect([$product->animalType?->name, $product->category?->name])->filter()->join(' · ') }}</p>
                            <h2 class="text-[15px] font-medium text-ink mt-1 leading-snug line-clamp-2 group-hover:text-primary-deep">{{ $product->displayName() }}</h2>
                            <x-market.producer :product="$product" class="mt-1" />
                            <div class="mt-auto pt-3 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1 sm:gap-2">
                                <div>
                                    @if($product->min_price !== null)
                                        <p class="text-[11px] text-ink-mute">from</p>
                                        <p class="text-[16px] sm:text-[18px] font-semibold tnum text-ink whitespace-nowrap">TZS {{ number_format($product->min_price) }}</p>
                                    @else
                                        <p class="text-[13px] text-ruby font-medium">Out of stock</p>
                                    @endif
                                </div>
                                <div class="sm:text-right text-[12px] text-ink-mute">
                                    <p>{{ $product->sellers_count }} {{ \Illuminate\Support\Str::plural('seller', $product->sellers_count) }}</p>
                                    @if($product->nearest_km !== null)
                                        <x-market.distance :km="$product->nearest_km" prefix="Nearest" class="mt-1" />
                                    @endif
                                </div>
                            </div>
                        </a>
                    </article>
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
                            @php
                                $seller = $store->merchant;
                                $brand = $seller->brandColor();
                                $accent = $seller->accentColor();
                            @endphp
                            <a href="{{ route('market.store', $store) }}" class="block group">
                                <x-ui.card padding="p-0" class="h-full overflow-hidden group-hover:border-primary/40 transition">
                                    {{-- The seller's own colours and logo, so customers recognise the store. --}}
                                    <div class="px-4 py-3 flex items-center gap-3" style="background: {{ $brand }}; color: {{ \App\Models\Merchant::textColorOn($brand) }}">
                                        @if($seller->logo())
                                            <img src="{{ $seller->logo()->getUrl() }}" alt="{{ $seller->business_name }} logo" class="h-11 w-11 rounded-lg bg-white object-contain p-0.5 shrink-0" loading="lazy">
                                        @else
                                            <span class="h-11 w-11 rounded-lg shrink-0 flex items-center justify-center text-[15px] font-semibold" style="background: {{ $accent }}; color: {{ \App\Models\Merchant::textColorOn($accent) }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($seller->business_name, 0, 2)) }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[15px] font-semibold truncate">{{ $seller->business_name }}</p>
                                            <p class="text-[12px] opacity-80 truncate">{{ $store->name }}</p>
                                        </div>
                                        @if($store->distance_km !== null)
                                            <x-market.distance :km="$store->distance_km" class="shrink-0 !bg-white/90 !text-ink" />
                                        @endif
                                    </div>
                                    <div class="h-1" style="background: {{ $accent }}"></div>
                                    <div class="p-4">
                                        <p class="text-[12px] text-ink-mute">{{ collect([$store->address, $store->city])->filter()->join(', ') }}</p>
                                        <p class="text-[12px] text-primary mt-2">{{ $store->offers_count }} {{ \Illuminate\Support\Str::plural('product', $store->offers_count) }} available &rarr;</p>
                                    </div>
                                </x-ui.card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
