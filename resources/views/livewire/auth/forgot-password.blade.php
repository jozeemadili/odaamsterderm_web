<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-6">Reset your password</h2>

    @if($sent)
        <p class="text-[14px] text-ink-secondary">
            If an account exists for <span class="font-medium">{{ $email }}</span>, we've sent a link to reset the password. Check the inbox (and spam folder) for the message.
        </p>
    @else
        <form wire:submit="send" class="space-y-4">
            <x-ui.input type="email" wire:model="email" label="Email" autofocus id="forgot_email" />
            @error('email') <p class="text-ruby text-[12px] -mt-3">{{ $message }}</p> @enderror

            <x-ui.button type="submit" target="send" class="w-full">
                Send reset link
            </x-ui.button>
        </form>
    @endif

    <p class="text-[13px] text-ink-mute mt-6 text-center">
        <a href="{{ route('login') }}" class="text-primary hover:text-primary-deep">Back to sign in</a>
    </p>
</div>
