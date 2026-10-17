<div x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true"
        class="inline-flex items-center gap-2 rounded-pill bg-brand-dark text-white px-4 py-2 text-[14px] font-medium hover:bg-brand-dark/90 shadow-sm">
        <span aria-hidden="true">✨</span> Use AI to place order
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="AI order assistant">
        <div class="absolute inset-0 bg-ink/40" x-on:click="open = false" x-show="open" x-transition.opacity></div>

        <div class="absolute inset-y-0 right-0 w-full sm:w-[460px] bg-canvas shadow-xl flex flex-col"
            x-show="open" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="px-4 py-3 border-b border-hairline flex items-center justify-between gap-3">
                <div>
                    <p class="text-[16px] font-medium">✨ AI order assistant</p>
                    <p class="text-[12px] text-ink-mute">Tell it what you need — English or Kiswahili.</p>
                </div>
                <div class="flex items-center gap-3">
                    @if($messages)
                        <button type="button" wire:click="startOver" class="text-[12px] text-ink-mute hover:text-ruby">Start over</button>
                    @endif
                    <button type="button" x-on:click="open = false" class="text-ink-mute hover:text-ink text-[22px] leading-none" aria-label="Close">×</button>
                </div>
            </div>

            @if($state !== 'ready')
                <div class="flex-1 p-6 text-center flex flex-col items-center justify-center gap-3">
                    @if($state === 'guest')
                        <p class="text-[15px] text-ink-secondary">Sign in with your customer account to order with the AI assistant.</p>
                        <a href="{{ route('login') }}" class="rounded-pill bg-primary text-ink font-medium px-4 py-2 text-[14px] hover:bg-primary-deep">Sign in</a>
                        <a href="{{ route('customer.register') }}" class="text-[13px] text-primary-deep underline">Create an account</a>
                    @else
                        <p class="text-[15px] text-ink-secondary">Only customer accounts can place marketplace orders. Seller and staff accounts cannot.</p>
                    @endif
                </div>
            @else
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3" id="ai-chat-log"
                    x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                    x-on:ai-scroll.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @if(! $messages)
                        <div class="rounded-xl bg-canvas-soft p-4 text-[14px] text-ink-secondary space-y-3">
                            <p>Describe what you need and I'll put together a basket from stores near you. You check it, then confirm the order in your cart.</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(['Feed for 200 broiler chicks for the first 2 weeks', 'Layers mash for 500 hens, the cheapest', 'Nahitaji chakula cha samaki tilapia'] as $example)
                                    <button type="button" wire:click="ask(@js($example))" class="text-left text-[13px] rounded-pill border border-hairline bg-canvas px-3 py-1.5 hover:border-primary/50">{{ $example }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @foreach($messages as $m)
                        @if($m['role'] === 'user')
                            <div class="flex justify-end"><p class="max-w-[85%] rounded-2xl rounded-br-sm bg-brand-dark text-white px-3.5 py-2 text-[14px] whitespace-pre-line">{{ $m['content'] }}</p></div>
                        @elseif($m['role'] === 'assistant')
                            <div class="flex"><p class="max-w-[90%] rounded-2xl rounded-bl-sm bg-canvas-soft text-ink px-3.5 py-2 text-[14px] whitespace-pre-line">{{ $m['content'] }}</p></div>
                        @else
                            <p class="text-center text-[12px] text-ink-mute">{{ $m['content'] }}</p>
                        @endif
                    @endforeach

                    @if($waiting)
                        <div class="flex"><p class="rounded-2xl rounded-bl-sm bg-canvas-soft text-ink-mute px-3.5 py-2 text-[14px] flex items-center gap-2">
                            <span class="inline-block h-3 w-3 rounded-full border-2 border-primary border-t-transparent animate-spin"></span> Checking live stock…
                        </p></div>
                    @endif

                    @if($basket)
                        <div class="rounded-xl border border-primary/30 bg-canvas">
                            <div class="px-3.5 py-2.5 border-b border-hairline flex items-center justify-between">
                                <p class="text-[14px] font-medium">Proposed basket</p>
                                <p class="text-[12px] text-ink-mute">{{ $stores }} {{ \Illuminate\Support\Str::plural('pickup store', $stores) }}</p>
                            </div>
                            <div class="divide-y divide-hairline">
                                @foreach($basket as $line)
                                    <div class="px-3.5 py-2.5 flex items-start gap-3" wire:key="ai-line-{{ $line['offer_id'] }}">
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ route('market.product', $line['product_slug']) }}" class="text-[14px] font-medium text-ink hover:text-primary-deep">{{ $line['name'] }}</a>
                                            <p class="text-[12px] text-ink-mute">{{ $line['store'] }}@if($line['distance']) · {{ $line['distance'] }}@endif</p>
                                            <p class="text-[12px] text-ink-mute tnum">TZS {{ number_format($line['unit_price']) }} / {{ $line['unit'] }} · {{ rtrim(rtrim(number_format($line['available'], 2, '.', ''), '0'), '.') }} available</p>
                                            @if($line['note'])<p class="text-[12px] text-ruby">{{ $line['note'] }}</p>@endif
                                        </div>
                                        <div class="flex flex-col items-end gap-1">
                                            <input type="number" min="0" step="any" max="{{ $line['available'] }}" wire:model.live.debounce.400ms="qty.{{ $line['offer_id'] }}"
                                                aria-label="Quantity of {{ $line['name'] }}"
                                                class="w-20 rounded-sm border border-hairline-input px-2 py-1 text-[14px] text-right tnum">
                                            <button type="button" wire:click="removeLine({{ $line['offer_id'] }})" class="text-[11px] text-ink-mute hover:text-ruby">Remove</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="px-3.5 py-2.5 border-t border-hairline flex items-center justify-between">
                                <p class="text-[13px] text-ink-mute">Total</p>
                                <p class="text-[17px] font-medium tnum">TZS {{ number_format($total) }}</p>
                            </div>
                            @error('basket')<p class="px-3.5 pb-2 text-[12px] text-ruby">{{ $message }}</p>@enderror
                            <div class="px-3.5 pb-3.5">
                                <button type="button" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart"
                                    class="w-full rounded-pill bg-primary text-ink font-medium px-4 py-2.5 text-[14px] hover:bg-primary-deep disabled:opacity-60">
                                    Add to cart &amp; review order
                                </button>
                                <p class="mt-2 text-[11px] text-ink-mute text-center">Nothing is ordered until you confirm in your cart. AI suggestions can be wrong — check quantities first.</p>
                            </div>
                        </div>
                    @endif
                </div>

                <form wire:submit="ask" class="border-t border-hairline p-3 flex items-end gap-2">
                    <label for="ai_input" class="sr-only">Message to the AI assistant</label>
                    <textarea id="ai_input" wire:model="input" rows="2" maxlength="{{ \App\Services\Ai\AiOrderAssistant::MAX_MESSAGE_LENGTH }}"
                        placeholder="e.g. I have 200 broiler chicks, 1 week old…"
                        x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
                        class="flex-1 resize-none rounded-lg border border-hairline-input px-3 py-2 text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        @disabled($waiting)></textarea>
                    <button type="submit" class="rounded-pill bg-brand-dark text-white px-4 py-2 text-[14px] font-medium disabled:opacity-50" @disabled($waiting)>Send</button>
                </form>
                @error('input')<p class="px-3 pb-2 text-[12px] text-ruby">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>
</div>
