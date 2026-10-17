<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}Odaamsterderm Hub — Animal products marketplace</title>
    <meta name="description" content="Compare prices for animal feeds, vaccines, medicines and equipment from approved sellers near you.">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas-soft text-ink font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-brand-dark text-white">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
            <a href="{{ route('market.home') }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('images/favicon-512.png') }}" alt="" class="h-8 w-8 rounded-md">
                <span class="text-[18px] font-light tracking-tight">Odaamsterderm<span class="font-medium text-primary-soft"> Hub</span></span>
            </a>

            @php($askAiOn = \App\Services\Ai\AiProductSearch::enabled())
            @php($inAiMode = $askAiOn && request()->filled('ask'))
            <form action="{{ route('market.home') }}" method="GET" class="order-3 w-full md:order-none md:w-auto md:flex-1 md:max-w-xl flex items-center gap-2"
                x-data="{ ai: {{ $inAiMode ? 'true' : 'false' }} }">
                <label for="market_q" class="sr-only">Search products</label>
                <input id="market_q" x-ref="q" type="search" name="{{ $inAiMode ? 'ask' : 'q' }}" :name="ai ? 'ask' : 'q'" maxlength="300"
                    value="{{ $inAiMode ? request('ask') : request('q') }}"
                    placeholder="{{ $inAiMode ? 'Describe what you need, e.g. feed for 200 broiler chicks' : 'Search feeds, vaccines, medicines…' }}"
                    :placeholder="ai ? 'Describe what you need, e.g. feed for 200 broiler chicks' : 'Search feeds, vaccines, medicines…'"
                    class="w-full rounded-pill bg-white/10 border text-white placeholder:text-white/50 px-4 py-2 text-[14px] focus:outline-none focus:border-primary-soft"
                    :class="ai ? 'border-primary-soft' : 'border-white/15'">
                @if($askAiOn)
                    <button type="button" x-on:click="ai = !ai; $nextTick(() => $refs.q.focus())" :aria-pressed="ai.toString()"
                        title="Ask AI: describe what you need in your own words (English or Kiswahili)"
                        class="shrink-0 rounded-pill px-3.5 py-2 text-[13px] font-medium border transition"
                        :class="ai ? 'bg-primary text-ink border-primary' : 'bg-white/10 text-white border-white/15 hover:border-primary-soft'">
                        ✨ Ask AI
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
                    <a href="{{ route('customer.register') }}" class="rounded-pill bg-primary text-ink font-medium px-3.5 py-1.5 hover:bg-primary-deep">Create account</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="border-t border-hairline bg-canvas">
        <div class="max-w-6xl mx-auto px-4 py-6 flex flex-wrap items-center justify-between gap-3 text-[13px] text-ink-mute">
            <p>Odaamsterderm Hub — livestock, poultry and aquaculture products from approved sellers.</p>
            <a href="{{ route('register') }}" class="text-primary hover:text-primary-deep">Sell on Odaamsterderm Hub &rarr;</a>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
