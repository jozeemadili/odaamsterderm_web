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

    {{-- Below the banner: the mobile app's layout. --}}
    @unless($hasFilters)
        @if($stores->isNotEmpty())
            {{-- Stores as story circles --}}
            <section aria-label="{{ $from ? 'Stores near you' : 'Stores' }}" class="-mx-1 flex gap-4 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
                @foreach($stores as $store)
                    @php
                        $seller = $store->merchant;
                        // The ring is drawn in the seller's own colours.
                        $ring = 'background: linear-gradient(45deg, '.$seller->accentColor().', '.$seller->brandColor().')';
                        $initials = \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim($store->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join(''));
                    @endphp
                    <a href="{{ route('market.store', $store) }}" class="group w-[78px] shrink-0 text-center" title="{{ $seller->business_name }} · {{ $store->name }}">
                        <span class="mx-auto block h-[72px] w-[72px] rounded-full p-[3px] transition group-hover:scale-105" style="{{ $ring }}">
                            <span class="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-white p-[3px]">
                                @if($seller->logo())
                                    <img src="{{ $seller->logo()->getUrl() }}" alt="" class="h-full w-full rounded-full object-contain" loading="lazy">
                                @else
                                    <span class="flex h-full w-full items-center justify-center rounded-full bg-primary-subtle text-[18px] font-extrabold text-primary">{{ $initials }}</span>
                                @endif
                            </span>
                        </span>
                        <span class="mt-1.5 block truncate text-[12px] font-medium text-ink">{{ $store->name }}</span>
                        @if($store->distance_km !== null)
                            <span class="block text-[11px] text-ink-mute tnum">{{ \App\Support\GeoPoint::formatKm((float) $store->distance_km) }}</span>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif
    @endunless

    {{-- Animal chips --}}
    @php
        $chip = 'shrink-0 inline-flex items-center rounded-pill border px-4 py-2 text-[14px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
        $chipOn = 'border-primary bg-primary text-white';
        $chipOff = 'border-hairline bg-canvas text-ink hover:border-primary/40';
    @endphp
    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
        <button wire:click="chooseAnimal(null)" class="{{ $chip }} {{ $animal === '' ? $chipOn : $chipOff }}">All animals</button>
        @foreach($animalGroups as $group)
            <button wire:click="chooseAnimal({{ $group->id }})" title="{{ $group->name }}" class="{{ $chip }} {{ $animal === (string) $group->id ? $chipOn : $chipOff }}">{{ $group->shortName() }}</button>
            @if($animal !== '' && ($animal === (string) $group->id || $group->children->contains('id', (int) $animal)))
                @foreach($group->children as $child)
                    <button wire:click="chooseAnimal({{ $child->id }})" title="{{ $child->name }}" class="{{ $chip }} text-[13px] {{ $animal === (string) $child->id ? $chipOn : $chipOff }}">{{ $child->shortName() }}</button>
                @endforeach
            @endif
        @endforeach
    </div>

    {{-- What it treats / is used for --}}
    @if(count($uses))
        <div class="-mx-1 mt-2 flex items-center gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
            <span class="shrink-0 text-[12.5px] font-semibold text-ink-mute">Used for</span>
            @foreach($uses as $u)
                <button wire:click="chooseUse(@js($u['code']))" title="{{ $u['label_sw'] }}"
                    class="shrink-0 inline-flex items-center rounded-pill border px-3 py-1.5 text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 {{ $use === $u['code'] ? 'border-leaf-deep bg-leaf text-ink' : 'border-hairline bg-canvas text-ink-secondary hover:border-leaf-deep/60' }}">{{ $u['label'] }}</button>
            @endforeach
        </div>
    @endif

    @unless($hasFilters)
        @if($featured->isNotEmpty())
            {{-- Amsterdam Group picks: small cards in one swipeable row --}}
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-display text-[18px] font-bold text-ink">{{ $featuredBrand }} picks</h2>
                    <button type="button" wire:click="$set('brand', @js($featuredBrand))" class="text-[14px] font-semibold text-primary hover:text-primary-deep">See all</button>
                </div>
                <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pb-2 [scrollbar-width:none]">
                    @foreach($featured as $product)
                        <a href="{{ route('market.product', $product->slug) }}" class="w-[150px] shrink-0 overflow-hidden rounded-[14px] border border-hairline bg-canvas hover:border-primary/40 transition">
                            <span class="block h-[120px] bg-canvas-soft p-2">
                                @if($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->displayName() }}" class="h-full w-full object-contain" loading="lazy">
                                @endif
                            </span>
                            <span class="block px-2.5 pb-3 pt-2">
                                <span class="block text-[13px] font-semibold leading-snug text-ink line-clamp-2">{{ $product->name }}</span>
                                <span class="mt-1 block font-display text-[14px] font-bold text-ink tnum">{{ $product->min_price !== null ? 'TZS '.number_format($product->min_price) : 'Not in stores yet' }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless

    <section id="products" class="scroll-mt-24 space-y-4">
            @if($askAi)
                {{-- Ask AI as a chat: earlier turns stay above, follow-ups go below. --}}
                <div class="rounded-xl border border-primary/30 bg-primary-subtle/30 p-4 space-y-3" @if($aiPending) wire:init="runAi" @endif>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[12px] uppercase tracking-wide font-medium text-primary-deep">✨ Ask AI</p>
                        <button type="button" wire:click="clearAi" class="text-[12px] text-ink-mute hover:text-ruby">{{ $aiThread ? 'End chat' : 'Clear AI search' }}</button>
                    </div>

                    @foreach($aiThread as $turn)
                        <div class="flex justify-end"><p class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary text-white px-3.5 py-2 text-[14px]">{{ $turn['question'] }}</p></div>
                        <p class="max-w-[90%] rounded-2xl rounded-bl-sm bg-canvas border border-hairline px-3.5 py-2 text-[14px] text-ink-secondary whitespace-pre-line">{{ $turn['answer'] }}</p>
                    @endforeach

                    <div class="flex justify-end"><p class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary text-white px-3.5 py-2 text-[14px]">{{ $ask }}</p></div>
                    <div class="max-w-[90%] rounded-2xl rounded-bl-sm bg-canvas border border-hairline px-3.5 py-2 text-[14px] text-ink-secondary">
                        @if($aiPending)
                            <span class="flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-full border-2 border-primary border-t-transparent animate-spin"></span>
                                Thinking and checking live stock…
                            </span>
                        @elseif($aiStatus === 'sign_in')
                            <a href="{{ route('login') }}" class="text-primary-deep font-medium underline">Sign in</a> to use Ask AI, or use the normal search.
                        @elseif($aiStatus === 'limited')
                            Ask AI is busy right now. Showing normal search results instead — please try again in a minute.
                        @elseif($aiStatus === 'unavailable')
                            Ask AI is not available right now. Showing normal search results instead.
                        @elseif($aiStatus === 'refused')
                            Ask AI can only help with animals, farming and products here. Try asking about what your animals need.
                        @else
                            <span class="whitespace-pre-line">{{ $ai['summary'] }}</span>
                        @endif
                    </div>

                    @if(! $aiPending && in_array($aiStatus, ['ok', 'off_topic', 'refused'], true))
                        <form wire:submit="askFollowUp" class="flex items-end gap-2 pt-1">
                            <label for="ai_follow_up" class="sr-only">Ask a follow-up question</label>
                            <textarea id="ai_follow_up" wire:model="followUp" rows="1" maxlength="{{ \App\Services\Ai\AiProductSearch::MAX_QUERY_LENGTH }}"
                                x-data x-on:keydown.enter.prevent="if (! $event.shiftKey) $el.form.requestSubmit()"
                                placeholder="Ask a follow-up, e.g. How much do I need for 300 chicks?"
                                class="flex-1 min-w-0 resize-none rounded-xl border border-hairline-input bg-canvas px-3.5 py-2.5 text-[14px] focus:outline-none focus:border-primary"></textarea>
                            <button type="submit" class="shrink-0 rounded-pill bg-primary text-white font-medium px-5 py-2.5 text-[14px] hover:bg-primary-deep" wire:loading.attr="disabled" wire:target="askFollowUp">Send</button>
                        </form>
                        @error('followUp')<p class="text-[12px] text-ruby">{{ $message }}</p>@enderror
                        <p class="text-[11px] text-ink-mute">AI suggestions can be wrong — check each product before you buy. Prices, stock and distances are live from the stores. The products below match your latest question.</p>
                    @endif
                </div>
            @endif

        {{-- Title, then the AI order assistant and Sort & filters, like the app's sort button --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="font-display text-[20px] font-bold text-ink">{{ $askAi ? 'AI picks for you' : ($q !== '' ? 'Results for “'.$q.'”' : 'All products') }}</h2>
                <p class="text-[13px] text-ink-mute">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if(\App\Services\Ai\AiOrderAssistant::enabled())
                    <livewire:market.ai-assistant />
                @endif
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
                    <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()"
                        class="inline-flex items-center gap-2 rounded-pill border border-hairline bg-canvas px-4 py-2.5 text-[14px] font-semibold text-ink hover:border-primary/40">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-4 w-4"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                        Sort &amp; filters
                        @if($hasFilters && ! $askAi && $q === '')<span class="rounded-pill bg-primary px-1.5 text-[11px] text-white">on</span>@endif
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                        class="absolute right-0 z-20 mt-2 w-[min(320px,calc(100vw-2rem))] rounded-2xl border border-hairline bg-canvas p-4 shadow-[0_20px_40px_rgba(42,10,15,0.15)]">
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
                    @if(count($uses))
                        <x-ui.select wire:model.live="use" label="Used for / treats" id="mk_use">
                            <option value="">Anything</option>
                            @foreach($uses as $u)<option value="{{ $u['code'] }}">{{ $u['label'] }}</option>@endforeach
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
                    </div>
                </div>
            </div>
        </div>

        {{-- While a search, filter or sort loads: skeleton posts. --}}
        @php($reloads = 'chooseAnimal,clearFilters,clearAi,askFollowUp,runAi,sort,category,brand,use,chooseUse,seller,inStock,minPrice,maxPrice')
        <div class="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" wire:loading.grid wire:target="{{ $reloads }}" aria-busy="true">
            @foreach(range(1, 6) as $i)
                <x-market.skeleton-post />
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" wire:loading.remove wire:target="{{ $reloads }}">
            @forelse($products as $product)
                @include('livewire.market.feed-post', ['product' => $product])
            @empty
                @if($aiPending)
                    @foreach(range(1, 3) as $i)
                        <x-market.skeleton-post />
                    @endforeach
                @else
                    <div class="col-span-full rounded-2xl border border-dashed border-hairline bg-canvas p-10 text-center text-ink-mute">
                        No products match. Try another search or clear the filters.
                    </div>
                @endif
            @endforelse
        </div>

        {{-- Infinite scroll: near the end, the next batch loads under skeleton posts. --}}
        @if($products->hasMorePages())
            <div wire:key="more-{{ $products->count() }}"
                x-data x-init="const io = new IntersectionObserver((e) => { if (e[0].isIntersecting) { io.disconnect(); $wire.loadMore() } }, { rootMargin: '900px' }); io.observe($el)">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach(range(1, 3) as $i)
                        <x-market.skeleton-post />
                    @endforeach
                </div>
            </div>
        @elseif($products->total() > 0)
            <div class="py-8 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-tr from-primary to-leaf text-white">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.79 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                </span>
                <p class="mt-2 font-display text-[16px] font-bold text-ink">You're all caught up</p>
                <p class="text-[13px] text-ink-mute">You've seen every product. Try another animal{{ \App\Services\Ai\AiProductSearch::enabled() ? ' or Ask AI' : '' }}.</p>
            </div>
        @endif
    </section>
</div>
