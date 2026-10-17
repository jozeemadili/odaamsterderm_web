<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Portal' }} · DukaFlow</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas-soft text-ink font-sans antialiased" x-data="{ sidebarOpen: false }">
    <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between bg-brand-dark px-4 py-3">
        <div class="flex items-center gap-2">
            <img src="{{ asset('images/favicon-512.png') }}" alt="DukaFlow" class="h-7 w-7 rounded-md shrink-0">
            <span class="text-[16px] font-light tracking-tight text-white">Duka<span class="font-medium text-primary-soft">Flow</span></span>
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
                        <img src="{{ asset('images/favicon-512.png') }}" alt="DukaFlow" class="h-7 w-7 rounded-md shrink-0">
                        <div class="min-w-0">
                            <span class="text-[17px] font-light tracking-tight text-white">Duka<span class="font-medium text-primary-soft">Flow</span></span>
                            <p class="text-[11px] text-white/40 mt-0.5 uppercase tracking-wide truncate">{{ auth()->user()->merchant?->business_name }}</p>
                        </div>
                    </div>
                    <button type="button" @click="sidebarOpen = false" class="lg:hidden shrink-0 p-1 text-white/60 hover:text-white" aria-label="Close menu">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <nav class="space-y-1 flex-1 overflow-y-auto" @click="sidebarOpen = false">
                    <a href="{{ route('portal.dashboard') }}" class="icon-dock-item {{ request()->routeIs('portal.dashboard') ? 'is-active' : '' }}">
                        <x-icon.dashboard class="h-4 w-4 shrink-0" /> Dashboard
                    </a>
                    <a href="{{ route('portal.notifications.index') }}" class="icon-dock-item {{ request()->routeIs('portal.notifications.*') ? 'is-active' : '' }}">
                        <x-icon.bell class="h-4 w-4 shrink-0" /> Notifications
                    </a>
                    <a href="{{ route('portal.pos.index') }}" class="icon-dock-item {{ request()->routeIs('portal.pos.*') ? 'is-active' : '' }}">
                        <x-icon.pos class="h-4 w-4 shrink-0" /> Point of Sale
                    </a>
                    <a href="{{ route('portal.stores.index') }}" class="icon-dock-item {{ request()->routeIs('portal.stores.*') ? 'is-active' : '' }}">
                        <x-icon.store class="h-4 w-4 shrink-0" /> Stores
                    </a>
                    <a href="{{ route('portal.stock-receipts.index') }}" class="icon-dock-item {{ request()->routeIs('portal.stock-receipts.*') ? 'is-active' : '' }}">
                        <x-icon.stock-receipt class="h-4 w-4 shrink-0" /> Stock Receipts
                    </a>
                    <a href="{{ route('portal.inventory.index') }}" class="icon-dock-item {{ request()->routeIs('portal.inventory.*') ? 'is-active' : '' }}">
                        <x-icon.inventory class="h-4 w-4 shrink-0" /> Inventory
                    </a>
                    <a href="{{ route('portal.expenses.index') }}" class="icon-dock-item {{ request()->routeIs('portal.expenses.*') ? 'is-active' : '' }}">
                        <x-icon.expenses class="h-4 w-4 shrink-0" /> Expenses
                    </a>
                    <a href="{{ route('portal.customers.index') }}" class="icon-dock-item {{ request()->routeIs('portal.customers.*') ? 'is-active' : '' }}">
                        <x-icon.customer class="h-4 w-4 shrink-0" /> Customers
                    </a>
                    <a href="{{ route('portal.sales.index') }}" class="icon-dock-item {{ request()->routeIs('portal.sales.*') ? 'is-active' : '' }}">
                        <x-icon.sales class="h-4 w-4 shrink-0" /> Sales
                    </a>
                    <a href="{{ route('portal.reports.product-performance') }}" class="icon-dock-item {{ request()->routeIs('portal.reports.*') ? 'is-active' : '' }}">
                        <x-icon.target class="h-4 w-4 shrink-0" /> Product Performance
                    </a>
                    <a href="{{ route('portal.invoices.index') }}" class="icon-dock-item {{ request()->routeIs('portal.invoices.*') ? 'is-active' : '' }}">
                        <x-icon.invoice class="h-4 w-4 shrink-0" /> Invoices
                    </a>
                    <a href="{{ route('portal.suppliers.index') }}" class="icon-dock-item {{ request()->routeIs('portal.suppliers.*') ? 'is-active' : '' }}">
                        <x-icon.suppliers class="h-4 w-4 shrink-0" /> Suppliers
                    </a>
                    <a href="{{ route('portal.payments.index') }}" class="icon-dock-item {{ request()->routeIs('portal.payments.*') ? 'is-active' : '' }}">
                        <x-icon.receipt class="h-4 w-4 shrink-0" /> Payments
                    </a>

                    @php
                        $inSystemSettings = request()->routeIs('portal.payment-methods.*')
                            || request()->routeIs('portal.kyc.*')
                            || request()->routeIs('portal.staff.*')
                            || request()->routeIs('portal.branding.*')
                            || request()->routeIs('portal.discount-limits.*');
                    @endphp
                    <div x-data="{ settingsOpen: {{ $inSystemSettings ? 'true' : 'false' }} }">
                        <button
                            type="button"
                            @click.stop="settingsOpen = !settingsOpen"
                            class="icon-dock-item w-full justify-between {{ $inSystemSettings ? 'is-active' : '' }}"
                        >
                            <span class="flex items-center gap-2.5">
                                <x-icon.settings class="h-4 w-4 shrink-0" /> System Settings
                            </span>
                            <svg
                                class="h-3.5 w-3.5 shrink-0 transition-transform duration-150"
                                :class="settingsOpen ? 'rotate-180' : ''"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            >
                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div x-show="settingsOpen" x-cloak class="mt-1 ml-3 pl-3 border-l border-white/10 space-y-1">
                            <a href="{{ route('portal.payment-methods.index') }}" class="icon-dock-item text-[13px] {{ request()->routeIs('portal.payment-methods.*') ? 'is-active' : '' }}">
                                <x-icon.wallet class="h-4 w-4 shrink-0" /> Payment Methods
                            </a>
                            <a href="{{ route('portal.kyc.index') }}" class="icon-dock-item text-[13px] {{ request()->routeIs('portal.kyc.*') ? 'is-active' : '' }}">
                                <x-icon.shield class="h-4 w-4 shrink-0" /> KYC Documents
                            </a>
                            @can('manage-own-staff')
                            <a href="{{ route('portal.staff.index') }}" class="icon-dock-item text-[13px] {{ request()->routeIs('portal.staff.*') ? 'is-active' : '' }}">
                                <x-icon.users class="h-4 w-4 shrink-0" /> Staff
                            </a>
                            <a href="{{ route('portal.branding.index') }}" class="icon-dock-item text-[13px] {{ request()->routeIs('portal.branding.*') ? 'is-active' : '' }}">
                                <x-icon.palette class="h-4 w-4 shrink-0" /> Branding
                            </a>
                            @endcan
                            @can('manage-discount-limits')
                            <a href="{{ route('portal.discount-limits.index') }}" class="icon-dock-item text-[13px] {{ request()->routeIs('portal.discount-limits.*') ? 'is-active' : '' }}">
                                <x-icon.percent class="h-4 w-4 shrink-0" /> Discount Limits
                            </a>
                            @endcan
                        </div>
                    </div>
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

                @if(auth()->user()->merchant)
                    @php
                        $notifyMerchant = auth()->user()->merchant;
                        $notifyLowStock = $notifyMerchant->lowStockItems()->limit(5)->get();
                        $notifyExpiring = $notifyMerchant->expiringSoonItems()->orderBy('expiry_date')->limit(5)->get();
                        $notifyDamaged = $notifyMerchant->damageReports()->with('inventoryItem')->latest('reported_at')->limit(5)->get();
                        $notifyCount = $notifyMerchant->lowStockItems()->count() + $notifyMerchant->expiringSoonItems()->count() + $notifyMerchant->damageReports()->count();
                    @endphp
                    <div class="relative" x-data="{ notifyOpen: false }">
                        <button
                            type="button"
                            @click.stop="notifyOpen = !notifyOpen"
                            @click.outside="notifyOpen = false"
                            class="relative p-2 rounded-full hover:bg-canvas-soft text-ink-secondary"
                            aria-label="Notifications"
                        >
                            <x-icon.bell class="h-5 w-5" />
                            @if($notifyCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 h-4 min-w-[16px] px-1 rounded-full bg-ruby text-white text-[10px] leading-4 text-center font-medium">{{ $notifyCount > 9 ? '9+' : $notifyCount }}</span>
                            @endif
                        </button>

                        <div x-show="notifyOpen" x-cloak class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-canvas border border-hairline rounded-lg shadow-lg z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-hairline">
                                <h3 class="text-[13px] font-medium text-ink">Notifications</h3>
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-hairline">
                                @foreach($notifyLowStock as $item)
                                    <a href="{{ route('portal.inventory.index') }}" class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-canvas-soft">
                                        <span class="mt-1 h-2 w-2 rounded-full bg-ruby shrink-0"></span>
                                        <span class="text-[12.5px] text-ink-secondary"><strong class="text-ink">{{ $item->name }}</strong> is low on stock — {{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }} left.</span>
                                    </a>
                                @endforeach
                                @foreach($notifyExpiring as $item)
                                    <a href="{{ route('portal.inventory.index') }}" class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-canvas-soft">
                                        <span class="mt-1 h-2 w-2 rounded-full bg-lemon shrink-0"></span>
                                        <span class="text-[12.5px] text-ink-secondary"><strong class="text-ink">{{ $item->name }}</strong> expires {{ $item->expiry_date->format('d M Y') }}.</span>
                                    </a>
                                @endforeach
                                @foreach($notifyDamaged as $report)
                                    <a href="{{ route('portal.notifications.index') }}" class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-canvas-soft">
                                        <span class="mt-1 h-2 w-2 rounded-full bg-ink-mute shrink-0"></span>
                                        <span class="text-[12.5px] text-ink-secondary"><strong class="text-ink">{{ $report->inventoryItem?->name ?? 'A product' }}</strong> — {{ rtrim(rtrim($report->quantity, '0'), '.') }} damaged.</span>
                                    </a>
                                @endforeach
                                @if($notifyCount === 0)
                                    <p class="px-4 py-6 text-center text-[12.5px] text-ink-mute">You're all caught up.</p>
                                @endif
                            </div>
                            @if($notifyCount > 0)
                                <a href="{{ route('portal.notifications.index') }}" class="block px-4 py-2.5 text-center text-[12.5px] text-primary hover:text-primary-deep border-t border-hairline">View all notifications &rarr;</a>
                            @endif
                        </div>
                    </div>
                @endif
            </header>

            @if(auth()->user()->merchant && auth()->user()->merchant->kyc_status !== \App\Models\Merchant::KYC_APPROVED)
                <div class="mb-4 rounded-lg bg-canvas-cream border border-lemon/20 text-lemon px-4 py-2.5 text-[14px]">
                    Your business verification is <strong>{{ str_replace('_', ' ', auth()->user()->merchant->kyc_status) }}</strong>.
                    Upload your KYC documents to get fully verified. <a href="{{ route('portal.kyc.index') }}" class="underline">Go to KYC Documents</a>.
                </div>
            @endif

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
