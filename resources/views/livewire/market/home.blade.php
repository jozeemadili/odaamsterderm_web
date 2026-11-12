<div class="space-y-6">
    @unless($hasFilters)
                <section class="relative overflow-hidden rounded-2xl border border-leaf/40 text-ink"
            style="background: radial-gradient(ellipse 65% 95% at 50% 25%, #ffffff 0%, #f7fbe9 35%, #d9ec9c 68%, #9fcd4f 100%);">
            {{-- Red and green wave along the bottom, cut from the banner artwork --}}
            <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-14 sm:h-20 lg:h-24 [mask-image:linear-gradient(to_bottom,transparent,black_35%)]"
                style="background: url('{{ asset('images/hero-wave.webp') }}') center 65% / 100% 250% no-repeat;"></div>
            <div class="relative px-6 pt-8 pb-20 sm:px-10 sm:pt-10 sm:pb-28 lg:pb-32 grid gap-8 lg:grid-cols-[1.4fr_1fr] items-center">
                <div>
                    <p class="text-[12px] uppercase tracking-[0.18em] text-primary font-semibold">Animal products marketplace</p>
                    <h1 class="mt-3 font-display text-[28px] sm:text-[44px] leading-[1.08] font-bold tracking-tight text-primary">Everything your animals need, from trusted sellers near you.</h1>
                    <p class="mt-3 text-[16px] leading-relaxed text-ink/80 max-w-xl">Compare prices on feeds, vaccines, medicines and equipment for poultry, cattle, goats, pigs and fish — from approved agrovets across Tanzania.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#products" class="rounded-pill bg-primary text-white font-medium px-5 py-2.5 text-[14px] shadow-sm hover:bg-primary-deep transition">Browse products</a>
                        @unless($from)
                            <button type="button" x-data x-on:click="document.querySelector('header [wire\\:click=openPicker]')?.click()" class="rounded-pill border border-primary/40 bg-white/60 text-primary px-5 py-2.5 text-[14px] hover:bg-white transition">Find stores near me</button>
                        @endunless
                    </div>
                </div>
                @if($spotlight)
                    <a href="{{ route('market.product', $spotlight->slug) }}" class="hidden sm:flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-[0_20px_40px_rgba(90,16,25,0.12)] hover:-translate-y-0.5 transition">
                        <span class="self-start rounded-pill bg-success-subtle text-success text-[12px] font-semibold px-2.5 py-1">From {{ $spotlight->brand }}</span>
                        <img src="{{ $spotlight->imageUrl() }}" alt="{{ $spotlight->displayName() }}" class="h-56 w-full object-contain">
                        <span class="flex items-end justify-between gap-3">
                            <span>
                                <span class="block text-[17px] font-semibold text-ink">{{ $spotlight->name }}</span>
                                <span class="block text-[13px] text-ink-mute">{{ $spotlight->variants->pluck('name')->join(' · ') }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block text-[11px] text-ink-mute">from</span>
                                <span class="block font-display text-[20px] font-bold text-ink tnum whitespace-nowrap">TZS {{ number_format($spotlight->min_price) }}</span>
                            </span>
                        </span>
                    </a>
                @endif
            </div>
        </section>
    @endunless

    @unless($hasFilters)
        @if(\App\Services\Ai\AiOrderAssistant::enabled())
            {{-- Ask the AI assistant: it builds the order from stores near you. --}}
            <section class="rounded-2xl bg-brand-dark text-white px-5 py-4 sm:px-7 sm:py-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span aria-hidden="true" class="h-11 w-11 shrink-0 rounded-full bg-leaf/20 text-leaf flex items-center justify-center">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 1.9 1.9.8-1.9.8L19 20.4l-.8-1.9-1.9-.8 1.9-.8z"/></svg>
                    </span>
                    <div>
                        <p class="font-display text-[17px] font-bold">Not sure what to buy?</p>
                        <p class="text-[14px] text-white/75">Tell our AI what your animals need. It builds the order from stores near you, or use <span class="text-leaf font-medium">Ask AI</span> in the search bar.</p>
                    </div>
                </div>
                <livewire:market.ai-assistant />
            </section>
        @endif

        {{-- Shop by animal --}}
        <section aria-label="Shop by animal" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($animalGroups as $group)
                <button type="button" wire:click="chooseAnimal({{ $group->id }})" class="text-left rounded-2xl bg-canvas border border-hairline p-4 hover:border-primary/50 hover:shadow-[0_8px_24px_rgba(42,10,15,0.06)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                    <span class="block font-display text-[16px] font-bold text-ink">{{ $group->shortName() }}</span>
                    <span class="block mt-1 text-[13px] text-ink-mute line-clamp-2">{{ $group->children->isNotEmpty() ? $group->children->map->shortName()->join(', ') : 'All products' }}</span>
                </button>
            @endforeach
        </section>

        @if($featured->isNotEmpty())
            {{-- Amsterdam Group's own range, first thing customers see. --}}
            <section class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-[12px] uppercase tracking-[0.16em] font-semibold text-primary">Featured</p>
                        <h2 class="font-display text-[24px] sm:text-[28px] font-bold tracking-tight text-ink">{{ $featuredBrand }} products</h2>
                    </div>
                    <button type="button" wire:click="$set('brand', @js($featuredBrand))" class="text-[14px] font-semibold text-primary hover:text-primary-deep">See all {{ $featuredTotal }} &rarr;</button>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    @foreach($featured as $product)
                        @include('livewire.market.product-card', ['product' => $product])
                    @endforeach
                </div>
            </section>
        @endif

        @if($partner->isNotEmpty())
            {{-- A partner's range in a block of its own. --}}
            <section class="flex flex-wrap gap-4">
                <div class="flex-[1_1_300px] rounded-2xl bg-primary text-white p-6 sm:p-7 flex flex-col gap-3">
                    <h2 class="font-display text-[24px] font-bold">{{ $partnerBrand }} feeds</h2>
                    <p class="text-[15px] text-primary-subtle leading-relaxed">Broiler and layer feeding programmes, from the first day to sale.</p>
                    <button type="button" wire:click="$set('brand', @js($partnerBrand))" class="self-start mt-auto rounded-pill bg-leaf text-ink font-semibold px-5 py-2.5 text-[14px] hover:bg-leaf-deep">Shop {{ $partnerBrand }} ({{ $partnerTotal }}) &rarr;</button>
                </div>
                <div class="flex-[2_1_480px] grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($partner as $product)
                        <a href="{{ route('market.product', $product->slug) }}" class="rounded-2xl bg-canvas border border-hairline p-3 flex items-center gap-4 hover:border-primary/40 transition">
                            @if($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->displayName() }}" class="h-24 w-20 object-contain shrink-0" loading="lazy">
                            @else
                                <span class="h-24 w-20 shrink-0 rounded-lg bg-canvas-soft"></span>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-[15px] font-semibold text-ink leading-snug">{{ $product->name }}</span>
                                <span class="block mt-1 font-display text-[16px] font-bold text-ink tnum">{{ $product->min_price !== null ? 'TZS '.number_format($product->min_price) : 'Not in stores yet' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless

    {{-- Animal type chips --}}
    @if($hasFilters)
    @php
        $chip = 'shrink-0 inline-flex items-center rounded-pill border-2 px-4 py-1.5 text-[14px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
        $chipOn = 'border-primary bg-primary-subtle text-primary';
        $chipOff = 'border-hairline bg-canvas text-ink hover:border-primary/40';
    @endphp
    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 [scrollbar-width:none]">
        <button wire:click="chooseAnimal(null)" class="{{ $chip }} {{ $animal === '' ? $chipOn : $chipOff }}">
            All animals
        </button>
        @foreach($animalGroups as $group)
            <button wire:click="chooseAnimal({{ $group->id }})" title="{{ $group->name }}" class="{{ $chip }} {{ $animal === (string) $group->id ? $chipOn : $chipOff }}">
                {{ $group->shortName() }}
            </button>
            @if($animal !== '' && ($animal === (string) $group->id || $group->children->contains('id', (int) $animal)))
                @foreach($group->children as $child)
                    <button wire:click="chooseAnimal({{ $child->id }})" title="{{ $child->name }}" class="{{ $chip }} text-[13px] {{ $animal === (string) $child->id ? $chipOn : $chipOff }}">
                        {{ $child->shortName() }}
                    </button>
                @endforeach
            @endif
        @endforeach
    </div>
    @endif

    <div id="products" class="scroll-mt-4 grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-5">
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

        <section class="min-w-0 space-y-5">
            @if($hasFilters && \App\Services\Ai\AiOrderAssistant::enabled())
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
                    @include('livewire.market.product-card', ['product' => $product])
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
                                        @if($store->offers_count > 0)
                                            <p class="text-[12px] text-primary mt-2">{{ $store->offers_count }} {{ \Illuminate\Support\Str::plural('product', $store->offers_count) }} available &rarr;</p>
                                        @else
                                            <p class="text-[12px] text-ink-mute mt-2">No products online yet &rarr;</p>
                                        @endif
                                    </div>
                                </x-ui.card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>

    @unless($hasFilters)
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach([
                ['Approved sellers only', 'Every store is checked before it can sell.'],
                ['Compare before you buy', 'See every store\'s price for the same product.'],
                ['Closest stores first', 'Set your location to see how far each one is.'],
            ] as [$title, $text])
                <div class="flex gap-3 rounded-2xl bg-canvas border border-hairline px-4 py-4">
                    <span aria-hidden="true" class="mt-0.5 h-6 w-6 shrink-0 rounded-full bg-leaf text-white flex items-center justify-center">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    </span>
                    <span><span class="block font-semibold text-ink">{{ $title }}</span><span class="text-ink-mute text-[13px]">{{ $text }}</span></span>
                </div>
            @endforeach
        </section>
    @endunless
</div>
