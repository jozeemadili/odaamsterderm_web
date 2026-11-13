<div class="max-w-3xl space-y-5">
    <h1 class="text-[24px] font-light tracking-tight">My account</h1>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
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
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-[16px] font-light mb-1">Orders</h2>
                <p class="text-[13px] text-ink-mute">Track your orders and pickup status.</p>
            </div>
            <a href="{{ route('market.orders') }}" class="text-[13px] text-primary hover:text-primary-deep">My orders &rarr;</a>
        </div>
    </x-ui.card>

    {{-- Required by the app stores: customers can delete their own account. --}}
    <x-ui.card>
        <div x-data="{ open: false }" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-[16px] font-light mb-1 text-ruby">Delete account</h2>
                    <p class="text-[13px] text-ink-mute">Removes your details and signs you out everywhere. <a href="{{ route('market.delete-account') }}" class="underline">What is deleted?</a></p>
                </div>
                <button type="button" x-on:click="open = !open" class="rounded-pill border border-ruby/40 px-4 py-2 text-[13px] font-medium text-ruby hover:bg-ruby/5">Delete account</button>
            </div>
            <form x-show="open" x-cloak wire:submit="deleteAccount" class="space-y-3 rounded-xl border border-ruby/30 bg-ruby/5 p-4">
                <p class="text-[13px] text-ink-secondary">This cannot be undone. Orders a shop has not started are cancelled.</p>
                @if(auth()->user()->has_password)
                    <x-ui.input type="password" wire:model="deletePassword" label="Your password" id="del_password" autocomplete="current-password" />
                    @error('deletePassword')<p class="text-[12px] text-ruby -mt-2">{{ $message }}</p>@enderror
                @else
                    <x-ui.input wire:model="deleteConfirm" label="Type DELETE to confirm" id="del_confirm" />
                    @error('deleteConfirm')<p class="text-[12px] text-ruby -mt-2">{{ $message }}</p>@enderror
                @endif
                <button type="submit" class="rounded-pill bg-ruby px-4 py-2 text-[13px] font-semibold text-white hover:opacity-90" wire:loading.attr="disabled" wire:target="deleteAccount">Delete my account</button>
            </form>
        </div>
    </x-ui.card>
</div>
