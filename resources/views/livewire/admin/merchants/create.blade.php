<div class="max-w-2xl">
    <x-ui.card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui.input wire:model="business_name" label="Business name" id="business_name" />
                <x-ui.input wire:model="owner_name" label="Owner name" id="owner_name" />
            </div>
            @error('business_name') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
            @error('owner_name') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-ui.input wire:model="phone" label="Phone" id="phone" />
                <x-ui.input type="email" wire:model="email" label="Email" id="email" />
            </div>
            @error('phone') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
            @error('email') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-ui.select wire:model="business_type_id" label="Business type" id="business_type_id">
                    <option value="">Select business type</option>
                    @foreach($businessTypes as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select wire:model="region_id" label="Region" id="region_id">
                    <option value="">Select region</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input wire:model="city" label="City" id="city" />
            </div>

            <label class="flex items-center gap-2 text-[13px] text-ink-secondary">
                <input type="checkbox" wire:model="markVerified" class="rounded border-hairline-input text-primary focus:ring-primary">
                Mark this merchant as KYC-verified immediately
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-ui.button type="submit" target="save">Create merchant</x-ui.button>
                <a href="{{ route('admin.merchants.index') }}" class="text-[13px] text-ink-mute hover:text-ink">Cancel</a>
            </div>

            <p class="text-[12px] text-ink-mute pt-2">
                A merchant owner account is created with a random password. Share the email with the owner and have them use "Forgot password?" on the sign-in page to set their own.
            </p>
        </form>
    </x-ui.card>
</div>
