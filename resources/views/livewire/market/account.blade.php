<div class="max-w-3xl space-y-5">
    <h1 class="text-[24px] font-light tracking-tight">My account</h1>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.input wire:model="name" label="Full name" id="acc_name" :error="$errors->first('name')" />
                <x-ui.input wire:model="phone" label="Phone" id="acc_phone" :error="$errors->first('phone')" />
                <x-ui.input type="email" wire:model="email" label="Email" id="acc_email" :error="$errors->first('email')" />
            </div>

            <div x-data="locationPicker({ lat: $wire.entangle('latitude'), lng: $wire.entangle('longitude') })">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <p class="text-[13px] text-ink-mute">Default location — nearby stores are ranked from here.</p>
                    <x-ui.button size="sm" variant="secondary" x-on:click="useMyLocation()">
                        <span x-show="!locating">Use my current location</span>
                        <span x-show="locating" x-cloak>Locating…</span>
                    </x-ui.button>
                </div>
                <div wire:ignore><div x-ref="map" class="h-72 rounded-lg border border-hairline z-0"></div></div>
                <p class="text-[12px] text-ruby mt-1" x-show="error" x-text="error"></p>
            </div>
            <x-ui.input wire:model="location_label" label="Name this place (optional)" id="acc_location_label" placeholder="e.g. My farm, Kibaha" />

            <x-ui.button type="submit" target="save">Save</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-[16px] font-light mb-1">Orders</h2>
        <p class="text-[13px] text-ink-mute">Ordering with store pickup is coming soon. You'll see your orders and their status here.</p>
    </x-ui.card>
</div>
