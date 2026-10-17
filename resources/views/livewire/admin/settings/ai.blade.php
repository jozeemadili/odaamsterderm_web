<div class="space-y-4 max-w-5xl">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
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

    <div class="grid sm:grid-cols-2 gap-4">
        @foreach(['Today' => $today, 'Last 30 days' => $month] as $label => $u)
            <x-ui.card>
                <p class="text-[12px] uppercase tracking-wide text-ink-mute">{{ $label }}</p>
                <div class="mt-2 flex items-end gap-6">
                    <div><p class="text-[24px] font-light tnum">{{ number_format($u['requests']) }}</p><p class="text-[12px] text-ink-mute">requests</p></div>
                    <div><p class="text-[24px] font-light tnum">{{ number_format($u['tokens']) }}</p><p class="text-[12px] text-ink-mute">tokens</p></div>
                    <div><p class="text-[24px] font-light tnum">${{ number_format($u['cost'], 2) }}</p><p class="text-[12px] text-ink-mute">estimated cost</p></div>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card padding="p-0">
        <h2 class="px-5 py-3 text-[15px] font-medium border-b border-hairline">Recent requests</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px] min-w-[640px]">
                <tbody class="divide-y divide-hairline">
                    @forelse($recent as $r)
                        <tr>
                            <td class="px-5 py-2.5 text-ink-mute whitespace-nowrap">{{ $r->created_at->diffForHumans() }}</td>
                            <td class="px-5 py-2.5"><span class="text-[11px] uppercase tracking-wide text-ink-mute mr-1.5">{{ $r->feature === 'order' ? 'Order' : 'Search' }}</span>{{ \Illuminate\Support\Str::limit($r->query, 80) }}</td>
                            <td class="px-5 py-2.5 text-ink-secondary">{{ $r->user?->name ?? 'Guest' }}</td>
                            <td class="px-5 py-2.5"><x-ui.badge :tone="$r->status === 'ok' ? 'success' : ($r->status === 'failed' ? 'danger' : 'warning')">{{ $r->status }}</x-ui.badge></td>
                            <td class="px-5 py-2.5 tnum text-ink-mute">{{ $r->results_count }} {{ $r->feature === 'order' ? 'basket lines' : 'results' }} · {{ number_format($r->input_tokens + $r->output_tokens) }} tokens</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-center text-ink-mute">No AI requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
