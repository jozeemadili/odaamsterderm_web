<div class="max-w-xl">
    <x-ui.card>
        <p class="text-[13px] text-ink-mute mb-5">
            Maximum discount each role can apply at the point of sale without needing approval from someone with a higher limit.
            The owner has no cap.
        </p>

        <form wire:submit="save" class="space-y-4">
            @foreach($roleLabels as $role => $label)
                @if($role !== 'merchant_owner')
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-[14px] text-ink-secondary">{{ $label }}</label>
                        <div class="w-32 flex items-center gap-2">
                            <input type="number" step="0.01" min="0" max="100" wire:model="limits.{{ $role }}" class="w-full rounded-sm border border-hairline-input bg-canvas text-[14px] px-3 py-1.5 text-right focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                            <span class="text-[13px] text-ink-mute">%</span>
                        </div>
                    </div>
                    @error("limits.{$role}") <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                @endif
            @endforeach

            <x-ui.button type="submit" target="save">Save limits</x-ui.button>
        </form>
    </x-ui.card>
</div>
