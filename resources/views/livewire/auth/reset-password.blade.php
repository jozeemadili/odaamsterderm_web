<div>
    <h2 class="text-[22px] font-light text-ink tracking-tight mb-6">Set a new password</h2>

    <form wire:submit="resetPassword" class="space-y-4">
        <x-ui.input type="email" wire:model="email" label="Email" id="reset_email" />
        @error('email') <p class="text-ruby text-[12px] -mt-3">{{ $message }}</p> @enderror

        <x-ui.input type="password" wire:model="password" label="New password" id="reset_password" autofocus />
        @error('password') <p class="text-ruby text-[12px] -mt-3">{{ $message }}</p> @enderror

        <x-ui.input type="password" wire:model="password_confirmation" label="Confirm new password" id="reset_password_confirmation" />

        <x-ui.button type="submit" target="resetPassword" class="w-full">
            Set password
        </x-ui.button>
    </form>
</div>
