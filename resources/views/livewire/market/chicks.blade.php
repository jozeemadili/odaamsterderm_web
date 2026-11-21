@php
    $fmtDate = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('D j M Y');
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $step = $chosenDate ? 4 : ($chosenHatchery ? 3 : ($chosenType ? 2 : 1));
@endphp
<div class="space-y-5">
    {{-- Header and steps --}}
    <div class="rounded-[22px] bg-canvas border border-hairline p-5 sm:p-6">
        <p class="text-[12px] uppercase tracking-[0.18em] text-primary font-semibold">Vifaranga · Day-old chicks</p>
        <h1 class="mt-2 font-display text-[24px] sm:text-[32px] leading-tight font-bold text-primary">Book day-old chicks from trusted hatcheries</h1>
        <p class="mt-2 text-[14.5px] text-ink/80 max-w-2xl">Choose the type, the hatchery and a hatch date, then book whole boxes of {{ $boxSize }} chicks. Collect them on hatch day at an Amsterdam Group store.</p>
        <ol class="mt-4 flex flex-wrap gap-2 text-[12.5px]">
            @foreach(['Chick type', 'Hatchery', 'Hatch date', 'Book'] as $i => $label)
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
                    <p class="mt-3 text-[13px] font-semibold {{ $t['hatcheries_count'] ? 'text-success' : 'text-ink-mute' }}">
                        @if($t['hatcheries_count'])
                            {{ $t['hatcheries_count'] }} {{ \Illuminate\Support\Str::plural('hatchery', $t['hatcheries_count']) }} · {{ $t['boxes_available'] }} {{ \Illuminate\Support\Str::plural('box', $t['boxes_available']) }} available
                        @else
                            No hatch dates open right now
                        @endif
                    </p>
                </button>
            @endforeach
        </div>
    @endif

    {{-- 2. Hatchery --}}
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

        <h3 class="text-[16px] font-semibold text-ink">Hatcheries with open hatch dates</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($chosenType['hatcheries'] as $h)
                <button wire:click="chooseHatchery('{{ $h['slug'] }}')" class="flex items-center gap-3 text-left rounded-[18px] border border-hairline bg-canvas p-4 hover:border-primary/40 transition">
                    <span class="h-12 w-12 shrink-0 rounded-full p-[2px] bg-gradient-to-tr from-leaf to-primary">
                        <span class="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-white">
                            @if($h['logo_url'])<img src="{{ $h['logo_url'] }}" alt="" class="h-full w-full object-contain">@else<span class="text-[13px] font-extrabold text-primary">{{ $initials($h['name']) }}</span>@endif
                        </span>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-bold text-ink truncate">{{ $h['name'] }}</span>
                        @if($h['location'])<span class="block text-[12px] text-ink-mute truncate">{{ $h['location'] }}</span>@endif
                        <span class="block text-[12.5px] font-semibold {{ $h['next_hatch_date'] ? 'text-success' : 'text-ruby' }}">
                            {{ $h['next_hatch_date'] ? 'Next hatch '.\Illuminate\Support\Carbon::parse($h['next_hatch_date'])->format('j M').' · '.$h['boxes_available'].' boxes' : 'Fully booked' }}
                        </span>
                    </span>
                </button>
            @empty
                <p class="text-ink-mute text-[14px]">No hatchery has an open {{ $chosenType['name'] }} hatch date right now. Please check again soon.</p>
            @endforelse
        </div>
    @endif

    {{-- 3. Hatch date --}}
    @if($step === 3)
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-hairline bg-white">
                @if($chosenHatchery['logo_url'])<img src="{{ $chosenHatchery['logo_url'] }}" alt="" class="h-full w-full object-contain">@else<span class="text-[13px] font-extrabold text-primary">{{ $initials($chosenHatchery['name']) }}</span>@endif
            </span>
            <div>
                <h2 class="font-display text-[20px] font-bold text-ink">{{ $chosenHatchery['name'] }} · {{ $chosenType['name'] }}</h2>
                @if($chosenHatchery['description'])<p class="text-[13px] text-ink-mute">{{ $chosenHatchery['description'] }}</p>@endif
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($chosenHatchery['dates'] as $d)
                @php($open = $d['status'] === 'open')
                <button @if($open) wire:click="chooseDate({{ $d['id'] }})" @endif @disabled(! $open)
                    class="text-left rounded-[16px] border p-4 transition {{ $open ? 'border-hairline bg-canvas hover:border-primary/40' : 'border-hairline bg-canvas-soft opacity-70 cursor-not-allowed' }}">
                    <p class="text-[12px] uppercase tracking-wide text-ink-mute">Hatch date</p>
                    <p class="font-display text-[18px] font-bold text-ink">{{ $fmtDate($d['hatch_date']) }}</p>
                    <p class="mt-1 text-[13px] {{ $open ? 'text-success font-semibold' : 'text-ruby font-semibold' }}">{{ $open ? $d['boxes_available'].' '.\Illuminate\Support\Str::plural('box', $d['boxes_available']).' left' : $d['status_label'] }}</p>
                    <p class="text-[12px] text-ink-mute">Order by {{ \Illuminate\Support\Carbon::parse($d['order_cutoff_date'])->format('D j M') }}</p>
                    @prices
                        @if($d['price_per_box'])<p class="mt-1 text-[13px] font-semibold text-ink tnum">TZS {{ number_format($d['price_per_box']) }} per box</p>@endif
                    @endprices
                    @if($d['notes'])<p class="mt-1 text-[12px] text-ink-secondary">{{ $d['notes'] }}</p>@endif
                </button>
            @endforeach
        </div>
    @endif

    {{-- 4. Book --}}
    @if($step === 4)
        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <x-ui.card>
                <form wire:submit="book" class="space-y-4">
                    <h2 class="text-[17px] font-semibold text-ink">{{ $chosenType['name'] }} chicks from {{ $chosenHatchery['name'] }}</h2>
                    <p class="text-[14px] text-ink-secondary">Hatch date <strong>{{ $fmtDate($chosenDate['hatch_date']) }}</strong> · order by {{ \Illuminate\Support\Carbon::parse($chosenDate['order_cutoff_date'])->format('D j M') }} · {{ $chosenDate['boxes_available'] }} {{ \Illuminate\Support\Str::plural('box', $chosenDate['boxes_available']) }} left</p>

                    <div>
                        <p class="text-[13px] text-ink-mute mb-1.5">Boxes ({{ $boxSize }} chicks each)</p>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="$set('boxes', {{ max(1, $boxes - 1) }})" class="h-10 w-10 rounded-full border border-hairline text-[18px] font-bold hover:border-primary/40" aria-label="One box less">−</button>
                            <input type="number" min="1" max="{{ $chosenDate['boxes_available'] }}" wire:model.live="boxes" class="w-20 rounded-sm border border-hairline-input px-2 py-2 text-center text-[16px] font-semibold tnum" aria-label="Boxes">
                            <button type="button" wire:click="$set('boxes', {{ min($chosenDate['boxes_available'], $boxes + 1) }})" class="h-10 w-10 rounded-full border border-hairline text-[18px] font-bold hover:border-primary/40" aria-label="One box more">+</button>
                            <span class="text-[14px] text-ink-secondary">= <strong class="tnum">{{ number_format(max(0, (int) $boxes) * $boxSize) }}</strong> chicks</span>
                        </div>
                        @error('boxes') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <x-ui.select wire:model="storeId" label="Collect at" id="chick_store" :error="$errors->first('storeId')">
                        @foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}{{ $s->city ? ' — '.$s->city : '' }}</option>@endforeach
                    </x-ui.select>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-ui.input wire:model="phone" label="Your phone" id="chick_phone" :error="$errors->first('phone')" />
                        <x-ui.input wire:model="note" label="Note (optional)" id="chick_note" placeholder="e.g. I'll come in the morning" />
                    </div>

                    @error('book') <div class="rounded-lg bg-ruby/10 text-ruby px-4 py-2.5 text-[14px]">{{ $message }}</div> @enderror

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="submit" target="book">{{ auth()->check() ? 'Book chicks' : 'Sign in to book' }}</x-ui.button>
                        <p class="text-[12.5px] text-ink-mute">The booking goes straight to the store. @prices Pay at pickup. @else The store confirms the price; pay at pickup. @endprices</p>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <p class="text-[12px] uppercase tracking-wide text-ink-mute">Your booking</p>
                <dl class="mt-2 space-y-2 text-[14px]">
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Type</dt><dd class="font-medium text-right">{{ $chosenType['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Hatchery</dt><dd class="font-medium text-right">{{ $chosenHatchery['name'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Hatch date</dt><dd class="font-medium text-right">{{ $fmtDate($chosenDate['hatch_date']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-mute">Chicks</dt><dd class="font-medium text-right tnum">{{ max(0, (int) $boxes) }} × {{ $boxSize }} = {{ number_format(max(0, (int) $boxes) * $boxSize) }}</dd></div>
                    @prices
                        @if($chosenDate['price_per_box'])
                            <div class="flex justify-between gap-3 border-t border-hairline pt-2"><dt class="text-ink-mute">Total</dt><dd class="font-display text-[18px] font-bold tnum">TZS {{ number_format(max(0, (int) $boxes) * $chosenDate['price_per_box']) }}</dd></div>
                        @endif
                    @endprices
                </dl>
            </x-ui.card>
        </div>
    @endif
</div>
