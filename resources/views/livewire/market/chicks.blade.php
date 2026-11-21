@php
    $fmtDate = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('D j M Y');
    $short = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('D j M');
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $km = fn ($k) => $k === null ? null : \App\Support\GeoPoint::formatKm((float) $k);
    $step = $chosenDay ? 4 : ($chosenAgent ? 3 : ($chosenType ? 2 : 1));
@endphp
<div class="space-y-5">
    {{-- Header and steps --}}
    <div class="rounded-[22px] bg-canvas border border-hairline p-5 sm:p-6">
        <p class="text-[12px] uppercase tracking-[0.18em] text-primary font-semibold">Vifaranga · Day-old chicks</p>
        <h1 class="mt-2 font-display text-[24px] sm:text-[32px] leading-tight font-bold text-primary">Book day-old chicks from an agent near you</h1>
        <p class="mt-2 text-[14.5px] text-ink/80 max-w-2xl">Choose the type, an agent and the day the chicks arrive, then book whole boxes of {{ $boxSize }} chicks. Collect them at the agent's store on that day.</p>
        <ol class="mt-4 flex flex-wrap gap-2 text-[12.5px]">
            @foreach(['Chick type', 'Agent', 'Arrival day', 'Book'] as $i => $label)
                <li class="inline-flex items-center gap-1.5 rounded-pill px-3 py-1 {{ $step === $i + 1 ? 'bg-primary text-white' : ($step > $i + 1 ? 'bg-success-subtle text-success' : 'bg-canvas-soft text-ink-mute') }}">
                    <span class="font-bold">{{ $i + 1 }}</span> {{ $label }}
                </li>
            @endforeach
        </ol>
    </div>

    @if($step > 1)
        <button wire:click="back" class="inline-flex items-center gap-1 text-[13.5px] font-semibold text-primary hover:text-primary-deep">← Back</button>
    @endif

    {{-- 1. Chick type --}}
    @if($step === 1)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($types as $t)
                <button wire:click="chooseType('{{ $t['code'] }}')" class="text-left rounded-[18px] border border-hairline bg-canvas p-5 hover:border-primary/40 hover:shadow-[0_10px_28px_rgba(42,10,15,0.08)] transition">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-[20px] font-bold text-ink">{{ $t['name'] }}</p>
                            <p class="text-[13px] text-ink-mute">{{ $t['name_sw'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-pill bg-leaf/15 px-2.5 py-1 text-[12px] font-semibold text-leaf-deep">{{ $t['purpose_label'] }}</span>
                    </div>
                    <p class="mt-3 text-[14px] text-ink-secondary">{{ $t['summary'] }}</p>
                    <p class="mt-3 text-[13px] font-semibold {{ $t['agents_count'] ? 'text-success' : 'text-ink-mute' }}">
                        @if($t['agents_count'])
                            {{ $t['agents_count'] }} {{ \Illuminate\Support\Str::plural('agent', $t['agents_count']) }} · {{ $t['boxes_available'] }} {{ \Illuminate\Support\Str::plural('box', $t['boxes_available']) }} open
                        @else
                            No order days open right now
                        @endif
                    </p>
                </button>
            @endforeach
        </div>
    @endif

    {{-- 2. Agent --}}
    @if($step === 2)
        <div class="rounded-[18px] border border-hairline bg-canvas p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-[20px] font-bold text-ink">{{ $chosenType['name'] }} <span class="text-[14px] font-normal text-ink-mute">· {{ $chosenType['name_sw'] }}</span></h2>
                <span class="rounded-pill bg-leaf/15 px-2.5 py-1 text-[12px] font-semibold text-leaf-deep">{{ $chosenType['purpose_label'] }}</span>
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 text-[14px] leading-relaxed">
                <p class="whitespace-pre-line text-ink-secondary">{{ $chosenType['description'] }}</p>
                <p class="whitespace-pre-line text-ink-secondary">{{ $chosenType['description_sw'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-[16px] font-semibold text-ink">Agents selling {{ $chosenType['name'] }} chicks</h3>
            @unless($hasLocation)<p class="text-[12.5px] text-ink-mute">Set your location at the top to see the nearest agents first.</p>@endunless
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($chosenType['agents'] as $a)
                <button wire:click="chooseAgent({{ $a['id'] }})" class="flex items-center gap-3 text-left rounded-[18px] border border-hairline bg-canvas p-4 hover:border-primary/40 transition">
                    <span class="h-12 w-12 shrink-0 rounded-full p-[2px] bg-gradient-to-tr from-leaf to-primary">
                        <span class="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-white">
                            @if($a['logo_url'])<img src="{{ $a['logo_url'] }}" alt="" class="h-full w-full object-contain">@else<span class="text-[13px] font-extrabold text-primary">{{ $initials($a['name']) }}</span>@endif
                        </span>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold text-ink truncate">{{ $a['name'] }}</span>
                        <span class="block text-[12px] text-ink-mute truncate">{{ $a['nearest_store'] }}{{ $km($a['nearest_km']) ? ' · '.$km($a['nearest_km']) : '' }}{{ $a['stores_count'] > 1 ? ' · '.$a['stores_count'].' stores' : '' }}</span>
                        <span class="block text-[12.5px] font-semibold {{ $a['next_date'] ? 'text-success' : 'text-ruby' }}">
                            {{ $a['next_date'] ? 'Next arrival '.$short($a['next_date']).' · '.$a['boxes_available'].' boxes open' : 'Fully booked' }}
                        </span>
                        <span class="block text-[12px] text-ink-mute truncate">{{ collect($a['hatcheries'])->pluck('name')->join(', ') }}</span>
                    </span>
                </button>
            @empty
                <p class="text-ink-mute text-[14px]">No agent has an open {{ $chosenType['name'] }} order day right now. Please check again soon.</p>
            @endforelse
        </div>
    @endif

    {{-- 3. Hatchery and arrival day --}}
    @if($step === 3)
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-hairline bg-white">
                @if($chosenAgent['logo_url'])<img src="{{ $chosenAgent['logo_url'] }}" alt="" class="h-full w-full object-contain">@else<span class="text-[13px] font-extrabold text-primary">{{ $initials($chosenAgent['name']) }}</span>@endif
            </span>
            <div>
                <h2 class="font-display text-[20px] font-bold text-ink">{{ $chosenAgent['name'] }} · {{ $chosenType['name'] }}</h2>
                <p class="text-[13px] text-ink-mute">Choose the day the chicks arrive.</p>
            </div>
        </div>
        @php
            $byDay = collect($chosenAgent['hatcheries'])
                ->flatMap(fn ($h) => collect($h['dates'])->map(fn ($d) => $d + ['hatchery' => $h]))
                ->groupBy(fn ($d) => substr($d['hatch_date'], 0, 10))->sortKeys();
            $firstOpen = $byDay->search(fn ($ds) => $ds->contains('status', 'open')) ?: $byDay->keys()->first();
        @endphp
        <div x-data="{ day: @js($firstOpen) }" class="space-y-4">
            {{-- Days as a compact strip, then who delivers that day --}}
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach($byDay as $date => $ds)
                    @php($c = \Illuminate\Support\Carbon::parse($date))
                    @php($anyOpen = $ds->contains('status', 'open'))
                    <button type="button" @click="day = @js($date)"
                        :class="day === @js($date) ? 'bg-primary text-white border-primary' : 'bg-canvas text-ink border-hairline {{ $anyOpen ? '' : 'opacity-50' }}'"
                        class="shrink-0 w-16 rounded-[14px] border py-2 text-center transition">
                        <span class="block text-[11.5px] opacity-75">{{ $c->format('D') }}</span>
                        <span class="block font-display text-[19px] font-extrabold leading-tight">{{ $c->format('j') }}</span>
                        <span class="block text-[11.5px] opacity-75">{{ $c->format('M') }}</span>
                    </button>
                @endforeach
            </div>
            @foreach($byDay as $date => $ds)
                <div x-show="day === @js($date)" x-cloak class="space-y-2">
                    <p class="text-[14px] font-semibold text-ink">{{ $short($date) }}</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($ds as $d)
                            @php($open = $d['status'] === 'open')
                            @php($h = $d['hatchery'])
                            <button @if($open) wire:click="chooseDay({{ $d['id'] }})" @endif @disabled(! $open)
                                class="flex items-center gap-3 text-left rounded-[16px] border p-3.5 transition {{ $open ? 'border-hairline bg-canvas hover:border-primary/40' : 'border-hairline bg-canvas-soft opacity-70 cursor-not-allowed' }}">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-display text-[15px] font-bold text-ink">{{ $h['name'] }}</span>
                                    <span class="block text-[12px] text-ink-mute">Collect at {{ count($h['pickup_stores']) > 2 ? 'any of '.count($h['pickup_stores']).' stores' : collect($h['pickup_stores'])->pluck('name')->join(' or ') }}</span>
                                    @prices
                                        @if($d['price_per_box'])<span class="block text-[12.5px] font-semibold text-ink tnum">TZS {{ number_format($d['price_per_box']) }} / box</span>@endif
                                    @endprices
                                </span>
                                <span class="shrink-0 rounded-pill px-2.5 py-1 text-[12px] font-semibold {{ $open ? 'bg-success-subtle text-success' : 'bg-ruby/10 text-ruby' }}">{{ $open ? $d['boxes_available'].' '.\Illuminate\Support\Str::plural('box', $d['boxes_available']).' left' : $d['status_label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- 4. Book --}}
    @if($step === 4)
        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <x-ui.card>
                <form wire:submit="book" class="space-y-4">
                    <h2 class="text-[17px] font-semibold text-ink">{{ $chosenType['name'] }} chicks · {{ $chosenHatchery['name'] }}</h2>
                    <p class="text-[14px] text-ink-secondary">From <strong>{{ $chosenAgent['name'] }}</strong> · arrive <strong>{{ $fmtDate($chosenDay['hatch_date']) }}</strong> · {{ $chosenDay['boxes_available'] }} {{ \Illuminate\Support\Str::plural('box', $chosenDay['boxes_available']) }} left</p>

                    <div>
                        <p class="text-[13px] text-ink-mute mb-1.5">Boxes ({{ $boxSize }} chicks each)</p>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="$set('boxes', {{ max(1, $boxes - 1) }})" class="h-10 w-10 rounded-full border border-hairline text-[18px] font-bold hover:border-primary/40" aria-label="One box less">−</button>
                            <input type="number" min="1" max="{{ $chosenDay['boxes_available'] }}" wire:model.live="boxes" class="w-20 rounded-sm border border-hairline-input px-2 py-2 text-center text-[16px] font-semibold tnum" aria-label="Boxes">
                            <button type="button" wire:click="$set('boxes', {{ min($chosenDay['boxes_available'], $boxes + 1) }})" class="h-10 w-10 rounded-full border border-hairline text-[18px] font-bold hover:border-primary/40" aria-label="One box more">+</button>
                            <span class="text-[14px] text-ink-secondary">= <strong class="tnum">{{ number_format(max(0, (int) $boxes) * $boxSize) }}</strong> chicks</span>
                        </div>
                        @error('boxes') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <x-ui.select wire:model.live="storeId" label="Collect at" id="chick_store" :error="$errors->first('storeId')">
                        @foreach($chosenHatchery['pickup_stores'] as $s)<option value="{{ $s['id'] }}">{{ $s['name'] }}{{ $km($s['distance_km']) ? ' — '.$km($s['distance_km']) : '' }}</option>@endforeach
                    </x-ui.select>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-ui.input wire:model="phone" label="Your phone" id="chick_phone" :error="$errors->first('phone')" />
                        <x-ui.input wire:model="note" label="Note (optional)" id="chick_note" placeholder="e.g. I'll come in the morning" />
                    </div>

                    @if($suggestions)
                        {{-- What these chicks will need first, from the same store. Quiet, nothing pre-ticked. --}}
                        <div class="rounded-[14px] border border-hairline bg-canvas-soft/60 p-3.5">
                            <p class="text-[13.5px] font-semibold text-ink">Ready for their first days</p>
                            <p class="text-[12px] text-ink-mute">Sized for {{ number_format(max(0, (int) $boxes) * $boxSize) }} chicks, collected with them.</p>
                            <div class="mt-2 divide-y divide-hairline">
                                @foreach($suggestions as $s)
                                    <label class="flex items-start gap-3 py-2.5 cursor-pointer" wire:key="sg-{{ $s['offer_id'] }}">
                                        <input type="checkbox" wire:model.live="extras.{{ $s['offer_id'] }}" class="mt-1 rounded border-hairline-input text-primary focus:ring-primary">
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[14px] font-medium text-ink">{{ $s['quantity'] }} × {{ $s['name'] }}</span>
                                            <span class="block text-[12px] text-ink-mute">{{ $s['reason'] }}{{ $s['amount'] ? ' · '.$s['amount'] : '' }}</span>
                                        </span>
                                        @prices
                                            @if($s['line_total'] !== null)<span class="shrink-0 text-[13px] tnum text-ink-secondary">TZS {{ number_format($s['line_total']) }}</span>@endif
                                        @endprices
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @error('book') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="submit" target="book">{{ auth()->check() ? 'Book chicks' : 'Sign in to book' }}</x-ui.button>
                        <p class="text-[12.5px] text-ink-mute">The booking goes straight to the agent. @prices Pay at pickup. @else The agent confirms the price; pay at pickup. @endprices</p>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <p class="text-[12px] uppercase tracking-wide text-ink-mute">Your booking</p>
                <dl class="mt-2 space-y-2 text-[14px]">
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Type</dt><dd class="font-medium text-right">{{ $chosenType['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Hatchery</dt><dd class="font-medium text-right">{{ $chosenHatchery['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Agent</dt><dd class="font-medium text-right">{{ $chosenAgent['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Arrive</dt><dd class="font-medium text-right">{{ $fmtDate($chosenDay['hatch_date']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Chicks</dt><dd class="font-medium text-right tnum">{{ max(0, (int) $boxes) }} × {{ $boxSize }} = {{ number_format(max(0, (int) $boxes) * $boxSize) }}</dd></div>
                    @prices
                        @if($chosenDay['price_per_box'])
                            <div class="flex justify-between gap-3 border-t border-hairline pt-2"><dt class="text-ink-mute">Total</dt><dd class="font-display text-[18px] font-bold tnum">TZS {{ number_format(max(0, (int) $boxes) * $chosenDay['price_per_box']) }}</dd></div>
                        @endif
                    @endprices
                </dl>
            </x-ui.card>
        </div>
    @endif
</div>
