<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-6">Register your business</h2>

    <a href="{{ route('auth.google.redirect') }}" class="w-full inline-flex items-center justify-center gap-2.5 rounded-pill border border-hairline-input bg-canvas text-ink text-[14px] font-medium px-4 py-2.5 hover:bg-canvas-soft transition">
        <x-icon.google class="h-4.5 w-4.5 shrink-0" />
        Continue with Google
    </a>

    <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-hairline"></div>
        <span class="text-[12px] text-ink-mute">or register with email</span>
        <div class="flex-1 h-px bg-hairline"></div>
    </div>

    <form wire:submit="register" class="space-y-3">
        <x-ui.input wire:model="business_name" label="Business name" id="business_name" />
        @error('business_name') <p class="text-ruby text-[12px] -mt-2">{{ $message }}</p> @enderror

        <x-ui.input wire:model="owner_name" label="Owner name" id="owner_name" />
        @error('owner_name') <p class="text-ruby text-[12px] -mt-2">{{ $message }}</p> @enderror

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-ui.input wire:model="phone" label="Phone" id="phone" />
                @error('phone') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
            </div>
            <x-ui.select wire:model="region_id" label="Region" id="region_id">
                <option value="">Select region</option>
                @foreach($regions as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <x-ui.select wire:model="business_type_id" label="Business type" id="business_type_id">
            <option value="">Select business type</option>
            @foreach($businessTypes as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input type="email" wire:model="email" label="Email" id="email" />
        @error('email') <p class="text-ruby text-[12px] -mt-2">{{ $message }}</p> @enderror

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-ui.input type="password" wire:model="password" label="Password" id="password" />
                @error('password') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
            </div>
            <x-ui.input type="password" wire:model="password_confirmation" label="Confirm password" id="password_confirmation" />
        </div>

        <x-ui.button type="submit" target="register" class="w-full mt-2">
            Create account
        </x-ui.button>
    </form>

    <p class="text-[13px] text-ink-mute mt-6 text-center">
        Already registered? <a href="{{ route('login') }}" class="text-primary hover:text-primary-deep">Sign in</a>
    </p>
</div>
