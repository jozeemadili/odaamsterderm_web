<div class="space-y-4 max-w-5xl">
    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="text-[16px] font-medium">Marketplace prices</h2>
                <p class="text-[13px] text-ink-mute mt-1">Shows product prices to customers on the website, in the app, in the cart and orders, and in AI answers. Keep it <strong>hidden</strong> while sellers set their prices in the portal and app; customers can still order, and the store confirms the price when they collect.</p>
            </div>
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <span class="text-[13px] font-medium {{ $showPrices ? 'text-primary-deep' : 'text-ink-mute' }}">{{ $showPrices ? 'Shown' : 'Hidden' }}</span>
                <input type="checkbox" wire:model.live="showPrices" class="sr-only peer" id="market_show_prices">
                <span class="relative h-6 w-11 rounded-full bg-hairline peer-checked:bg-primary transition after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>
    </x-ui.card>

    @if($demo)
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-ink px-4 py-2.5 text-[14px]">
            <strong>Demo mode (no real AI, free).</strong> No Anthropic API key is set, so a simple word-matching assistant answers instead of Claude. It understands common English and Kiswahili words and uses simple quantity rules — good for testing the screens and the ordering flow, not for judging answer quality. Add <code class="text-[13px]">ANTHROPIC_API_KEY=…</code> to the server's <code class="text-[13px]">.env</code> to switch to real AI.
        </div>
    @endif

    @unless($configured)
        <div class="rounded-lg bg-canvas-cream border border-lemon/30 text-ink px-4 py-2.5 text-[14px]">
            No Anthropic API key is set. Add <code class="text-[13px]">ANTHROPIC_API_KEY=…</code> to the server's <code class="text-[13px]">.env</code>. Until then Ask AI shows normal keyword results and the order assistant tells customers it is not available.
        </div>
    @endunless

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="text-[16px] font-medium">Ask AI search</h2>
                <p class="text-[13px] text-ink-mute mt-1">Shows the <strong>Ask AI</strong> button on the marketplace search. Customers describe what they need in English or Kiswahili and the assistant picks matching products from live stock. Prices, stock and distances always come from the marketplace, never from the AI.</p>
            </div>
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <span class="text-[13px] font-medium {{ $searchEnabled ? 'text-primary-deep' : 'text-ink-mute' }}">{{ $searchEnabled ? 'Shown' : 'Hidden' }}</span>
                <input type="checkbox" wire:model.live="searchEnabled" class="sr-only peer" id="ai_search_enabled">
                <span class="relative h-6 w-11 rounded-full bg-hairline peer-checked:bg-primary transition after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>

        <form wire:submit="save" class="mt-5 pt-5 border-t border-hairline grid sm:grid-cols-2 gap-4 items-end">
            <label class="flex items-center gap-2 text-[14px] text-ink-secondary">
                <input type="checkbox" wire:model="guestsAllowed" class="rounded border-hairline-input text-primary focus:ring-primary">
                Allow visitors who are not signed in
            </label>
            <div>
                <x-ui.input type="number" min="0" wire:model="dailyLimit" label="Daily request limit (all customers, 0 = no limit)" id="ai_daily_limit" />
                @error('dailyLimit')<p class="text-[12px] text-ruby mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2 flex items-center justify-between gap-3">
                <p class="text-[12px] text-ink-mute">Model: <span class="tnum">{{ $model }}</span> · each customer can ask {{ \App\Services\Ai\AiUsage::PER_MINUTE }} times a minute · repeated questions are answered from cache for 30 minutes.</p>
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 class="text-[16px] font-medium">Use AI to place order</h2>
                <p class="text-[13px] text-ink-mute mt-1">Shows the <strong>Use AI to place order</strong> button on the marketplace home page. Signed-in customers chat with the assistant, which proposes a basket from live offers (product, store, quantity). The assistant never orders by itself: the customer adds the basket to the cart and confirms the order there, where stock is checked and reserved as usual.</p>
            </div>
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <span class="text-[13px] font-medium {{ $orderEnabled ? 'text-primary-deep' : 'text-ink-mute' }}">{{ $orderEnabled ? 'Shown' : 'Hidden' }}</span>
                <input type="checkbox" wire:model.live="orderEnabled" class="sr-only peer" id="ai_order_enabled">
                <span class="relative h-6 w-11 rounded-full bg-hairline peer-checked:bg-primary transition after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
            </label>
        </div>
        <p class="text-[12px] text-ink-mute mt-3">Customers must be signed in to use it. The daily request limit above covers both AI features.</p>
    </x-ui.card>

    {{-- Usage and cost --}}
    @php($tzs = fn (float $usd) => 'TSh '.number_format($usd * $rate))
    <section class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-[17px] font-semibold text-ink">Usage and cost</h2>
                <p class="text-[12.5px] text-ink-mute">Estimated from Anthropic's list prices for {{ $model }}, shown in Tanzanian shillings. The real bill is on the Anthropic console.</p>
            </div>
            <form wire:submit="saveRate" class="flex items-end gap-2">
                <div class="w-36"><x-ui.input type="number" step="1" min="100" wire:model="usdRate" label="1 USD = … TZS" id="usd_rate" :error="$errors->first('usdRate')" /></div>
                <x-ui.button size="sm" variant="secondary" type="submit" target="saveRate">Save rate</x-ui.button>
            </form>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            @foreach(['Today' => $today, 'This month' => $thisMonth, 'Last 30 days' => $month] as $label => $u)
                <div class="rounded-[18px] border border-hairline bg-canvas p-4">
                    <p class="text-[11.5px] font-semibold uppercase tracking-wide text-ink-mute">{{ $label }}</p>
                    <p class="mt-2 font-display text-[28px] font-bold leading-none tnum text-ink">{{ $tzs($u['usd']) }}</p>
                    <p class="mt-1 text-[12.5px] text-ink-mute tnum">≈ ${{ number_format($u['usd'], 2) }} estimated</p>
                    <div class="mt-3 grid grid-cols-3 gap-2 border-t border-hairline pt-3 text-[12px]">
                        <div><p class="font-semibold tnum text-ink">{{ number_format($u['requests']) }}</p><p class="text-ink-mute">requests</p></div>
                        <div><p class="font-semibold tnum text-ink">{{ $u['tokens'] >= 1000000 ? number_format($u['tokens'] / 1000000, 1).'M' : number_format($u['tokens']) }}</p><p class="text-ink-mute">tokens</p></div>
                        <div><p class="font-semibold tnum text-ink">{{ $u['requests'] ? $tzs($u['per_request']) : '—' }}</p><p class="text-ink-mute">per request</p></div>
                    </div>
                    @if($u['failed'])<p class="mt-2 text-[12px] text-ruby">{{ $u['failed'] }} failed</p>@endif
                </div>
            @endforeach
        </div>

        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-[14px] font-semibold text-ink">Daily cost, last 30 days</h3>
                <p class="text-[12px] text-ink-mute">
                    @foreach($byFeature as $f => $n){{ $f === 'order' ? 'Place order' : 'Ask AI' }}: {{ number_format($n) }}@if(! $loop->last) · @endif @endforeach
                </p>
            </div>
            @php($max = max(0.0001, $days->max('usd')))
            <div class="mt-4 flex h-32 items-end gap-[3px]" role="img" aria-label="Daily AI cost for the last 30 days">
                @foreach($days as $day => $d)
                    <div class="group relative flex-1">
                        <div class="w-full rounded-t-[3px] {{ $d['usd'] > 0 ? 'bg-primary/70 group-hover:bg-primary' : 'bg-hairline' }}" style="height: {{ $d['usd'] > 0 ? max(4, round(120 * $d['usd'] / $max)) : 2 }}px"></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-ink px-2 py-1 text-[11px] text-white group-hover:block">{{ \Illuminate\Support\Carbon::parse($day)->format('D j M') }} · {{ $d['requests'] }} req · {{ $tzs($d['usd']) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-ink-mute"><span>{{ \Illuminate\Support\Carbon::parse($days->keys()->first())->format('j M') }}</span><span>Today</span></div>
            <p class="mt-3 text-[11.5px] text-ink-mute">
                Prices per million tokens: input ${{ number_format(\App\Services\Ai\AiPricing::PRICES[$model][0] ?? 4, 2) }}, output ${{ number_format(\App\Services\Ai\AiPricing::PRICES[$model][1] ?? 20, 2) }}, cached prompt read ${{ number_format(\App\Services\Ai\AiPricing::PRICES[$model][2] ?? 0.2, 2) }}.
                @if($cacheSince) Cached tokens are counted separately from {{ \Illuminate\Support\Carbon::parse($cacheSince)->format('j M Y') }}; earlier requests are estimated at the full input price, so they read a little high.@else Requests before this update counted cached tokens at the full input price, so older figures read a little high.@endif
            </p>
        </x-ui.card>
    </section>

    {{-- Requests --}}
    <x-ui.card padding="p-0">
        <div class="flex flex-wrap items-end gap-3 border-b border-hairline px-5 py-4">
            <h2 class="mr-auto text-[15px] font-semibold text-ink">Requests</h2>
            <div class="w-full sm:w-64"><x-ui.input wire:model.live.debounce.400ms="search" placeholder="Name, phone, email or question…" id="ai_search" /></div>
            <div><x-ui.input type="date" wire:model.live="from" label="From" id="ai_from" /></div>
            <div><x-ui.input type="date" wire:model.live="to" label="To" id="ai_to" /></div>
            <x-ui.select wire:model.live="feature" label="Feature" id="ai_feature">
                <option value="">All</option><option value="search">Ask AI</option><option value="order">Place order</option>
            </x-ui.select>
            <x-ui.select wire:model.live="status" label="Status" id="ai_status">
                <option value="">All</option><option value="ok">OK</option><option value="refused">Refused</option><option value="failed">Failed</option>
            </x-ui.select>
            @if($filtered)<button type="button" wire:click="clearFilters" class="pb-2 text-[12.5px] text-ink-mute hover:text-ink">Clear</button>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                    <tr>
                        <th class="px-5 py-2.5 font-normal">When</th>
                        <th class="px-5 py-2.5 font-normal">Customer</th>
                        <th class="px-5 py-2.5 font-normal">Question</th>
                        <th class="px-5 py-2.5 font-normal">Status</th>
                        <th class="px-5 py-2.5 font-normal text-right">Tokens</th>
                        <th class="px-5 py-2.5 font-normal text-right">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($recent as $r)
                        <tr class="align-top hover:bg-canvas-soft/60" wire:key="ai-{{ $r->id }}">
                            <td class="whitespace-nowrap px-5 py-2.5">
                                <span class="block text-ink">{{ $r->created_at->format('j M Y') }}</span>
                                <span class="block text-[12px] text-ink-mute">{{ $r->created_at->format('H:i') }} · {{ $r->created_at->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-2.5">
                                @if($r->user)
                                    <span class="block font-medium text-ink">{{ $r->user->name }}</span>
                                    @if($r->user->phone)<a href="tel:{{ $r->user->phone }}" class="block text-[12px] text-primary hover:text-primary-deep tnum">{{ $r->user->phone }}</a>@else<span class="block text-[12px] text-ink-mute">No phone</span>@endif
                                    @if($r->user->email)<span class="block max-w-[200px] truncate text-[12px] text-ink-mute">{{ $r->user->email }}</span>@endif
                                @else
                                    <span class="block text-ink-secondary">Guest</span>
                                    <span class="block text-[12px] text-ink-mute">Not signed in</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                <span class="mr-1.5 rounded-pill bg-canvas-soft px-2 py-0.5 text-[11px] font-semibold text-ink-secondary">{{ $r->feature === 'order' ? 'Place order' : 'Ask AI' }}</span>
                                <span class="text-ink">{{ \Illuminate\Support\Str::limit($r->query, 110) }}</span>
                                <span class="mt-0.5 block text-[12px] text-ink-mute">{{ $r->results_count }} {{ $r->feature === 'order' ? 'basket lines' : 'results' }}@if($r->duration_ms) · {{ number_format($r->duration_ms / 1000, 1) }} s @endif</span>
                                @if($r->error)<span class="block text-[12px] text-ruby">{{ \Illuminate\Support\Str::limit($r->error, 120) }}</span>@endif
                            </td>
                            <td class="px-5 py-2.5"><x-ui.badge :tone="$r->status === 'ok' ? 'success' : ($r->status === 'failed' ? 'danger' : 'warning')">{{ $r->status === 'ok' ? 'OK' : ucfirst($r->status) }}</x-ui.badge></td>
                            <td class="px-5 py-2.5 text-right tnum text-ink-secondary">{{ number_format($r->totalTokens()) }}</td>
                            <td class="px-5 py-2.5 text-right tnum">
                                <span class="block text-ink">{{ $tzs($r->costUsd()) }}</span>
                                <span class="block text-[11.5px] text-ink-mute">${{ number_format($r->costUsd(), 4) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">{{ $filtered ? 'No requests match these filters.' : 'No AI requests yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recent->hasPages())<div class="border-t border-hairline px-5 py-3">{{ $recent->links() }}</div>@endif
    </x-ui.card>
</div>
