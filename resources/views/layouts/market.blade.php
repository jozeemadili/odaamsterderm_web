<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}Amsterdam Digital — Animal products marketplace</title>
    <meta name="description" content="Compare prices for animal feeds, vaccines, medicines and equipment from approved sellers near you.">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas-soft text-ink font-sans antialiased min-h-screen flex flex-col">
    <header class="md:sticky md:top-0 z-30 bg-brand-dark/95 backdrop-blur text-white shadow-[0_1px_0_rgba(255,255,255,0.06),0_4px_16px_rgba(42,10,15,0.25)]">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
            <a href="{{ route('market.home') }}" class="flex items-center gap-2 shrink-0">
                <x-brand aria-label="Amsterdam Digital" />
            </a>

            @php($askAiOn = \App\Services\Ai\AiProductSearch::enabled())
            @php($inAiMode = $askAiOn && request()->filled('ask'))
            {{-- One search bar with Ask AI inside, like the app: Enter searches,
                 "Ask AI" sends the same words to the AI as a question. --}}
            <form action="{{ route('market.home') }}" method="GET" x-data
                class="order-3 w-full md:order-none md:w-auto md:flex-1 md:max-w-2xl flex items-center gap-2 rounded-pill bg-white/10 border border-white/15 pl-4 pr-1 py-1 focus-within:border-primary-soft transition">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-[18px] w-[18px] shrink-0 text-white/60"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <label for="market_q" class="sr-only">Search products or ask a question</label>
                <input id="market_q" x-ref="q" type="search" name="{{ $inAiMode ? 'ask' : 'q' }}" maxlength="300"
                    value="{{ $inAiMode ? request('ask') : request('q') }}"
                    placeholder="{{ $askAiOn ? 'Search feeds, vaccines, medicines… or ask AI' : 'Search feeds, vaccines, medicines…' }}"
                    class="min-w-0 flex-1 bg-transparent py-1.5 text-[14px] text-white placeholder:text-white/50 focus:outline-none">
                @if($askAiOn)
                    <button type="submit" x-on:click="$refs.q.name = 'ask'"
                        title="Ask AI: describe what you need in your own words (English or Kiswahili)"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-pill bg-primary px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-primary-deep transition">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-leaf"><path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/><path d="M19 15l.8 1.9 1.9.8-1.9.8L19 20.4l-.8-1.9-1.9-.8 1.9-.8z"/></svg>
                        Ask AI
                    </button>
                @endif
            </form>

            <div class="ml-auto flex items-center gap-3 text-[13px]">
                <livewire:market.location-bar />
                @auth
                    @if(auth()->user()->isCustomer())
                        <livewire:market.cart-link />
                        <a href="{{ route('market.account') }}" class="text-white/80 hover:text-white">My account</a>
                    @else
                        <a href="{{ auth()->user()->homeUrl() }}" class="text-white/80 hover:text-white">{{ auth()->user()->isInternal() ? 'Admin' : 'Seller portal' }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-white/60 hover:text-white">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-white/80 hover:text-white">Sign in</a>
                    <a href="{{ route('customer.register') }}" class="rounded-pill bg-leaf text-ink font-medium px-3.5 py-1.5 hover:bg-leaf-deep">Create account</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mt-8 bg-brand-dark text-white/70">
        <div class="max-w-6xl mx-auto px-4 py-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr] text-[14px]">
            <div>
                <a href="{{ route('market.home') }}" class="flex items-center gap-2">
                    <x-brand aria-label="Amsterdam Digital" />
                </a>
                <p class="mt-3 max-w-sm text-[13px] leading-relaxed">Livestock, poultry and aquaculture products from approved sellers. Compare prices and buy from the stores closest to you.</p>
            </div>
            <div>
                <p class="text-[12px] uppercase tracking-[0.14em] text-white/50 font-medium mb-3">Buy</p>
                <ul class="space-y-2">
                    <li><a href="{{ route('market.home') }}" class="hover:text-white">All products</a></li>
                    @auth
                        @if(auth()->user()->isCustomer())
                            <li><a href="{{ route('market.account') }}" class="hover:text-white">My account</a></li>
                        @endif
                    @else
                        <li><a href="{{ route('customer.register') }}" class="hover:text-white">Create an account</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white">Sign in</a></li>
                    @endauth
                </ul>
            </div>
            <div>
                <p class="text-[12px] uppercase tracking-[0.14em] text-white/50 font-medium mb-3">Sell</p>
                <ul class="space-y-2">
                    <li><a href="{{ route('register') }}" class="text-leaf hover:text-white">Sell on Amsterdam Digital &rarr;</a></li>
                    <li><a href="{{ route('login', ['as' => 'seller']) }}" class="hover:text-white">Seller sign in</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <p class="max-w-6xl mx-auto px-4 py-4 text-[12px] text-white/40">&copy; {{ date('Y') }} Amsterdam Digital. All rights reserved.</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
