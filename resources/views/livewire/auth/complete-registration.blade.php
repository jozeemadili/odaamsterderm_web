<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-1">Register your business</h2>
    <p class="text-[13px] text-ink-mute mb-6">Signed in with Google as <span class="text-ink font-medium">{{ $email }}</span>. Just a few more details to set up your shop.</p>

    <form wire:submit="register" class="space-y-3">
        <x-ui.input wire:model="business_name" label="Business name" id="cr_business_name" />
        @error('business_name') <p class="text-ruby text-[12px] -mt-2">{{ $message }}</p> @enderror

        <x-ui.input wire:model="owner_name" label="Owner name" id="cr_owner_name" />
        @error('owner_name') <p class="text-ruby text-[12px] -mt-2">{{ $message }}</p> @enderror

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-ui.input wire:model="phone" label="Phone" id="cr_phone" />
                @error('phone') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
            </div>
            <x-ui.select wire:model="region_id" label="Region" id="cr_region_id">
                <option value="">Select region</option>
                @foreach($regions as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <x-ui.select wire:model="business_type_id" label="Business type" id="cr_business_type_id">
            <option value="">Select business type</option>
            @foreach($businessTypes as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input type="email" value="{{ $email }}" label="Email" id="cr_email" disabled class="opacity-60" />

        <x-ui.button type="submit" target="register" class="w-full mt-2">
            Create account
        </x-ui.button>
    </form>

    <p class="text-[13px] text-ink-mute mt-6 text-center">
        Already registered? <a href="{{ route('login') }}" class="text-primary hover:text-primary-deep">Sign in</a>
    </p>
</div>
