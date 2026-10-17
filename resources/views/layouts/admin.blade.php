<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} · Odaamsterderm Hub</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas-soft text-ink font-sans antialiased" x-data="{ sidebarOpen: false }">
    <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between bg-brand-dark px-4 py-3">
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/favicon-512.png') }}" alt="Odaamsterderm Hub" class="h-7 w-7 rounded-md shrink-0">
            <span class="text-[16px] font-light tracking-tight text-white">Odaamsterderm<span class="font-medium text-primary-soft"> Hub</span></span>
        </div>
        <button type="button" @click="sidebarOpen = true" class="p-1.5 text-white/80 hover:text-white" aria-label="Open menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="lg:hidden fixed inset-0 z-40 bg-black/40"
        style="display: none;"
    ></div>

    <div class="flex min-h-screen gap-4 p-4">
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-50 w-64 p-4 transition-transform duration-200 lg:static lg:z-auto lg:w-60 lg:p-0 lg:shrink-0"
        >
            <div class="bg-brand-dark rounded-xl p-4 flex flex-col h-full lg:h-[calc(100vh-2rem)] lg:sticky lg:top-4">
                <div class="px-2 py-2 mb-4 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <img src="{{ asset('images/favicon-512.png') }}" alt="Odaamsterderm Hub" class="h-7 w-7 rounded-md shrink-0">
                        <div class="min-w-0">
                            <span class="text-[17px] font-light tracking-tight text-white">Odaamsterderm<span class="font-medium text-primary-soft"> Hub</span></span>
                            <p class="text-[11px] text-white/40 mt-0.5 uppercase tracking-wide">Admin console</p>
                        </div>
                    </div>
                    <button type="button" @click="sidebarOpen = false" class="lg:hidden shrink-0 p-1 text-white/60 hover:text-white" aria-label="Close menu">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <nav class="space-y-1 flex-1 overflow-y-auto" @click="sidebarOpen = false">
                    <a href="{{ route('admin.dashboard') }}" class="icon-dock-item {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                        <x-icon.dashboard class="h-4 w-4 shrink-0" /> Dashboard
                    </a>

                    @can('view-merchants')
                    <a href="{{ route('admin.merchants.index') }}" class="icon-dock-item {{ request()->routeIs('admin.merchants.*') ? 'is-active' : '' }}">
                        <x-icon.merchants class="h-4 w-4 shrink-0" /> Merchants
                    </a>
                    @endcan

                    @can('review-kyc')
                    <a href="{{ route('admin.kyc.index') }}" class="icon-dock-item {{ request()->routeIs('admin.kyc.*') ? 'is-active' : '' }}">
                        <x-icon.shield class="h-4 w-4 shrink-0" /> KYC Review
                    </a>
                    @endcan

                    @can('verify-payments')
                    <a href="{{ route('admin.payments.index') }}" class="icon-dock-item {{ request()->routeIs('admin.payments.*') ? 'is-active' : '' }}">
                        <x-icon.receipt class="h-4 w-4 shrink-0" /> Payment Verification
                    </a>
                    @endcan

                    @can('manage-users')
                    <a href="{{ route('admin.users.index') }}" class="icon-dock-item {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                        <x-icon.users class="h-4 w-4 shrink-0" /> Staff &amp; Roles
                    </a>
                    @endcan

                    @can('manage-lookup-data')
                    <a href="{{ route('admin.business-types.index') }}" class="icon-dock-item {{ request()->routeIs('admin.business-types.*') ? 'is-active' : '' }}">
                        <x-icon.settings class="h-4 w-4 shrink-0" /> Business Types
                    </a>
                    @endcan

                    @can('monitor-marketplace')
                    <p class="px-3 pt-4 pb-1 text-[10px] uppercase tracking-wider text-white/35">Marketplace</p>
                    <a href="{{ route('admin.marketplace.index') }}" class="icon-dock-item {{ request()->routeIs('admin.marketplace.index') ? 'is-active' : '' }}">
                        <x-icon.target class="h-4 w-4 shrink-0" /> Overview
                    </a>
                    <a href="{{ route('admin.orders.index') }}" class="icon-dock-item {{ request()->routeIs('admin.orders.*') ? 'is-active' : '' }}">
                        <x-icon.receipt class="h-4 w-4 shrink-0" /> Orders
                    </a>
                    <a href="{{ route('admin.stores.index') }}" class="icon-dock-item {{ request()->routeIs('admin.stores.*') ? 'is-active' : '' }}">
                        <x-icon.store class="h-4 w-4 shrink-0" /> Stores
                    </a>
                    <a href="{{ route('admin.marketplace.exceptions') }}" class="icon-dock-item {{ request()->routeIs('admin.marketplace.exceptions') ? 'is-active' : '' }}">
                        <x-icon.shield class="h-4 w-4 shrink-0" /> Exceptions
                    </a>
                    <a href="{{ route('admin.reports.marketplace') }}" class="icon-dock-item {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}">
                        <x-icon.sales class="h-4 w-4 shrink-0" /> Reports
                    </a>
                    @endcan

                    @can('manage-catalog')
                    <p class="px-3 pt-4 pb-1 text-[10px] uppercase tracking-wider text-white/35">Catalog</p>
                    <a href="{{ route('admin.products.index') }}" class="icon-dock-item {{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}">
                        <x-icon.inventory class="h-4 w-4 shrink-0" /> Products
                    </a>
                    <a href="{{ route('admin.animal-types.index') }}" class="icon-dock-item {{ request()->routeIs('admin.animal-types.*') ? 'is-active' : '' }}">
                        <x-icon.target class="h-4 w-4 shrink-0" /> Animal Types
                    </a>
                    <a href="{{ route('admin.product-categories.index') }}" class="icon-dock-item {{ request()->routeIs('admin.product-categories.*') ? 'is-active' : '' }}">
                        <x-icon.settings class="h-4 w-4 shrink-0" /> Categories
                    </a>
                    <a href="{{ route('admin.producers.index') }}" class="icon-dock-item {{ request()->routeIs('admin.producers.*') ? 'is-active' : '' }}">
                        <x-icon.store class="h-4 w-4 shrink-0" /> Producers
                    </a>
                    @endcan

                    @can('manage-platform-settings')
                    <p class="px-3 pt-4 pb-1 text-[10px] uppercase tracking-wider text-white/35">Settings</p>
                    <a href="{{ route('admin.settings.ai') }}" class="icon-dock-item {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                        <x-icon.settings class="h-4 w-4 shrink-0" /> AI Assistant
                    </a>
                    @endcan

                    @can('view-audit-log')
                    <a href="{{ route('admin.audit-log.index') }}" class="icon-dock-item {{ request()->routeIs('admin.audit-log.*') ? 'is-active' : '' }}">
                        <x-icon.log class="h-4 w-4 shrink-0" /> Audit Log
                    </a>
                    @endcan
                </nav>

                <div class="pt-3 mt-3 border-t border-white/10">
                    <p class="px-2 text-[13px] text-white/80 truncate">{{ auth()->user()->name }}</p>
                    @if(auth()->user()->roleLabel())
                        <p class="px-2 text-[11px] text-primary-soft/80 uppercase tracking-wide">{{ auth()->user()->roleLabel() }}</p>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="mt-1">
                        @csrf
                        <button type="submit" class="icon-dock-item w-full text-left text-ruby/80 hover:text-ruby hover:bg-ruby/10">
                            <x-icon.logout class="h-4 w-4 shrink-0" /> Log out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <header class="flex items-center justify-between mb-6 px-1 pt-2">
                <h1 class="text-[28px] font-light tracking-tight text-ink">{{ $title ?? 'Dashboard' }}</h1>
            </header>

            @if(session('status'))
                <div class="mb-4 rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">
                    {{ session('status') }}
                </div>
            @endif

            <main class="pb-10">
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
