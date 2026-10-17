<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-6">Sign in</h2>

    <a href="{{ route('auth.google.redirect') }}" class="w-full inline-flex items-center justify-center gap-2.5 rounded-pill border border-hairline-input bg-canvas text-ink text-[14px] font-medium px-4 py-2.5 hover:bg-canvas-soft transition">
        <x-icon.google class="h-4.5 w-4.5 shrink-0" />
        Continue with Google
    </a>

    <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-hairline"></div>
        <span class="text-[12px] text-ink-mute">or sign in with email</span>
        <div class="flex-1 h-px bg-hairline"></div>
    </div>

    <form wire:submit="login" class="space-y-4">
        <x-ui.input type="email" wire:model="email" label="Email" autofocus id="email" />
        @error('email') <p class="text-ruby text-[12px] -mt-3">{{ $message }}</p> @enderror

        <x-ui.input type="password" wire:model="password" label="Password" id="password" />
        @error('password') <p class="text-ruby text-[12px] -mt-3">{{ $message }}</p> @enderror

        <div class="text-right -mt-1">
            <a href="{{ route('password.request') }}" class="text-[12px] text-primary hover:text-primary-deep">Forgot password?</a>
        </div>

        <x-ui.button type="submit" target="login" class="w-full">
            Sign in
        </x-ui.button>
    </form>

    <p class="text-[13px] text-ink-mute mt-6 text-center">
        New merchant? <a href="{{ route('register') }}" class="text-primary hover:text-primary-deep">Register your business</a>
    </p>
</div>
