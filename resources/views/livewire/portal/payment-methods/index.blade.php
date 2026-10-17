<div class="space-y-4">
    <x-ui.button wire:click="$toggle('showForm')">
        {{ $showForm ? 'Cancel' : '+ Add payment method' }}
    </x-ui.button>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="addMethod" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <x-ui.input wire:model="name" label="Name" placeholder="e.g. Airtel Money" id="pm_name" />
                <x-ui.select wire:model="type" label="Type" id="pm_type">
                    <option value="mobile_money">Mobile money</option>
                    <option value="bank">Bank</option>
                    <option value="card">Card</option>
                    <option value="cash">Cash</option>
                    <option value="other">Other</option>
                </x-ui.select>
                <x-ui.button type="submit" target="addMethod">Save</x-ui.button>
            </form>
            @error('name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Method</th>
                    <th class="px-5 py-3 font-normal">Type</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($methods as $method)
                    <tr class="hover:bg-canvas-soft/60 {{ ! $method->is_active ? 'opacity-50' : '' }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <x-payment-method-badge :method="$method" />
                                <span class="text-ink font-medium">{{ $method->name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary capitalize">{{ str_replace('_', ' ', $method->type) }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$method->is_active ? 'success' : 'neutral'">{{ $method->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button wire:click="toggleActive({{ $method->id }})" class="text-[12px] {{ $method->is_active ? 'text-ruby hover:underline' : 'text-primary hover:underline' }}">
                                {{ $method->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-ink-mute">No payment methods yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
