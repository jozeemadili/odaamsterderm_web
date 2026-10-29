@props(['photos' => [], 'alt' => '', 'labels' => ['Front', 'Back'], 'href' => null])

{{-- Instagram-style photo slider: swipe on phones, arrows on hover, dots below. --}}
@php($photos = array_values($photos))
<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-canvas-soft group/carousel']) }}
    x-data="{
        shown: 0,
        count: {{ count($photos) }},
        go(i) { this.$refs.track.scrollTo({ left: i * this.$refs.track.clientWidth, behavior: 'smooth' }) },
        sync() { this.shown = Math.round(this.$refs.track.scrollLeft / this.$refs.track.clientWidth) },
    }">
    @if(count($photos))
        <div x-ref="track" x-on:scroll.debounce.50ms="sync()"
            class="flex h-full w-full overflow-x-auto snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($photos as $i => $url)
                @php($img = '<img src="'.e($url).'" alt="'.e($alt.(count($photos) > 1 ? ' — '.($labels[$i] ?? 'photo '.($i + 1)) : '')).'" class="h-full w-full object-cover" loading="lazy" draggable="false">')
                @if($href)
                    <a href="{{ $href }}" tabindex="-1" class="h-full w-full shrink-0 snap-center">{!! $img !!}</a>
                @else
                    <div class="h-full w-full shrink-0 snap-center">{!! $img !!}</div>
                @endif
            @endforeach
        </div>

        @if(count($photos) > 1)
            <span class="absolute top-2.5 right-2.5 rounded-pill bg-ink/60 text-white text-[11px] font-medium px-2 py-0.5 backdrop-blur-sm tnum"
                x-text="(shown + 1) + '/' + count">1/{{ count($photos) }}</span>

            <button type="button" x-on:click.prevent.stop="go(shown - 1)" x-show="shown > 0" x-cloak aria-label="Previous photo"
                class="absolute left-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-white/90 text-ink shadow-card flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 focus:opacity-100 transition">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" x-on:click.prevent.stop="go(shown + 1)" x-show="shown < count - 1" aria-label="Next photo"
                class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-white/90 text-ink shadow-card flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 focus:opacity-100 transition">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 0 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
            </button>

            <div class="absolute bottom-2.5 inset-x-0 flex justify-center gap-1.5">
                @foreach($photos as $i => $url)
                    <button type="button" x-on:click.prevent.stop="go({{ $i }})" aria-label="Show {{ $labels[$i] ?? 'photo '.($i + 1) }}"
                        class="h-1.5 rounded-full transition-all"
                        :class="shown === {{ $i }} ? 'w-4 bg-white' : 'w-1.5 bg-white/60'"></button>
                @endforeach
            </div>
        @endif
    @else
        @if($href)
            <a href="{{ $href }}" tabindex="-1" class="h-full w-full flex items-center justify-center"><x-icon.inventory class="h-10 w-10 text-ink-mute/40" /></a>
        @else
            <div class="h-full w-full flex items-center justify-center"><x-icon.inventory class="h-10 w-10 text-ink-mute/40" /></div>
        @endif
    @endif
</div>
