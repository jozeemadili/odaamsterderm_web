<div class="relative">
    @unless($current)
        {{-- No location yet: ask the browser once per visit, so distances show straight away. --}}
        <div x-data x-init="
            if (!navigator.geolocation || sessionStorage.getItem('ohAutoLocation')) return;
            sessionStorage.setItem('ohAutoLocation', '1');
            const go = () => navigator.geolocation.getCurrentPosition(
                (p) => $wire.detected(p.coords.latitude, p.coords.longitude),
                () => {},
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
            navigator.permissions
                ? navigator.permissions.query({ name: 'geolocation' }).then((r) => { if (r.state !== 'denied') go(); }).catch(go)
                : go();
        "></div>
    @endunless
    <button type="button" wire:click="openPicker"
        class="inline-flex items-center gap-1.5 rounded-pill border border-white/20 px-3 py-1.5 text-white/90 hover:border-primary-soft max-w-[220px]">
        <svg class="h-4 w-4 shrink-0 text-primary-soft" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" stroke="currentColor" stroke-width="1.75"/><circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.75"/></svg>
        <span class="truncate">{{ $current ? 'Near: '.($current->label ?? 'my location') : 'Set your location' }}</span>
    </button>

    @if($open)
        <div class="fixed inset-0 z-[1000] bg-ink/40 flex items-start justify-center p-4 pt-20" wire:click.self="$set('open', false)">
            <div class="bg-canvas text-ink rounded-xl shadow-xl w-full max-w-lg p-5"
                x-data="locationPicker({ lat: $wire.entangle('latitude'), lng: $wire.entangle('longitude') })">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-[17px] font-medium">Where are you?</h2>
                    <button type="button" wire:click="$set('open', false)" class="text-ink-mute hover:text-ink" aria-label="Close">&times;</button>
                </div>
                <p class="text-[13px] text-ink-mute mb-4">We use it only to show the nearest stores and distances.</p>

                <button type="button" class="w-full rounded-pill bg-primary text-ink font-medium px-4 py-2 text-[14px] hover:bg-primary-deep"
                    x-on:click="if (!navigator.geolocation) { error = 'This browser cannot share its location — pick a point on the map.'; return; }
                        locating = true;
                        navigator.geolocation.getCurrentPosition(
                            (p) => { locating = false; $wire.useDeviceLocation(p.coords.latitude, p.coords.longitude); },
                            () => { locating = false; error = 'Location permission was denied — pick a point on the map instead.'; },
                            { enableHighAccuracy: true, timeout: 10000 })">
                    <span x-show="!locating">Use my current location</span>
                    <span x-show="locating" x-cloak>Locating…</span>
                </button>
                <p class="text-[12px] text-ruby mt-2" x-show="error" x-text="error"></p>

                <p class="text-[13px] text-ink-mute mt-4 mb-2">Or tap the map to choose a location:</p>
                <div wire:ignore><div x-ref="map" class="h-64 rounded-lg border border-hairline z-0"></div></div>
                @error('latitude') <p class="text-[12px] text-ruby mt-2">{{ $message }}</p> @enderror

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[180px]">
                        <x-ui.input wire:model="label" label="Name this place (optional)" id="location_label" placeholder="e.g. My farm, Kibaha" />
                    </div>
                    <x-ui.button wire:click="savePicked" target="savePicked">Use this point</x-ui.button>
                </div>
                @if($current)
                    <button type="button" wire:click="clearLocation" class="mt-3 text-[12px] text-ink-mute hover:text-ruby">Clear location</button>
                @endif
            </div>
        </div>
    @endif
</div>
