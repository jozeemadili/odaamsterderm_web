<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-1">Create your account</h2>
    <p class="text-[13px] text-ink-mute mb-6">Compare prices, find the nearest store and order animal feeds, medicines and equipment.</p>

    <a href="{{ route('auth.google.redirect', ['as' => 'customer']) }}" class="w-full inline-flex items-center justify-center gap-2.5 rounded-pill border border-hairline-input bg-canvas text-ink text-[14px] font-medium px-4 py-2.5 hover:bg-canvas-soft transition">
        <x-icon.google class="h-4.5 w-4.5 shrink-0" />
        Continue with Google
    </a>

    <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-hairline"></div>
        <span class="text-[12px] text-ink-mute">or sign up with email</span>
        <div class="flex-1 h-px bg-hairline"></div>
    </div>

    <form wire:submit="register" class="space-y-3">
        <x-ui.input wire:model="name" label="Full name" id="name" autofocus :error="$errors->first('name')" />
        <x-ui.input wire:model="phone" label="Phone" id="phone" placeholder="+255…" :error="$errors->first('phone')" />
        <x-ui.input type="email" wire:model="email" label="Email" id="email" :error="$errors->first('email')" />
        <div class="grid grid-cols-2 gap-3">
            <x-ui.input type="password" wire:model="password" label="Password" id="password" :error="$errors->first('password')" />
            <x-ui.input type="password" wire:model="password_confirmation" label="Confirm password" id="password_confirmation" />
        </div>

        <x-ui.button type="submit" target="register" class="w-full mt-2">Create account</x-ui.button>
    </form>

    <p class="text-[13px] text-ink-mute mt-6 text-center">
        Already have an account? <a href="{{ route('login') }}" class="text-primary hover:text-primary-deep">Sign in</a>
    </p>
    <p class="text-[13px] text-ink-mute mt-2 text-center">
        Selling animal products? <a href="{{ route('register') }}" class="text-primary hover:text-primary-deep">Register your business</a>
    </p>
</div>
