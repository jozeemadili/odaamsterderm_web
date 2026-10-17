<div class="space-y-4">
    <div>
        <x-ui.button wire:click="$toggle('showForm')">
            {{ $showForm ? 'Cancel' : '+ Add staff member' }}
        </x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <x-ui.input wire:model="name" label="Name" id="staff_name" />
                <x-ui.input type="email" wire:model="email" label="Email" id="staff_email" />
                <x-ui.input wire:model="phone" label="Phone" id="staff_phone" />
                <x-ui.select wire:model="role" label="Role" id="staff_role">
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" target="create">Create</x-ui.button>
            </form>
            @error('name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            @error('email') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Name</th>
                    <th class="px-5 py-3 font-normal">Email</th>
                    <th class="px-5 py-3 font-normal">Phone</th>
                    <th class="px-5 py-3 font-normal">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($staff as $user)
                    <tr>
                        <td class="px-5 py-3 text-ink font-medium">{{ $user->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $user->email }}</td>
                        <td class="px-5 py-3 text-ink-secondary tnum">{{ $user->phone }}</td>
                        <td class="px-5 py-3">
                            <select wire:change="updateRole({{ $user->id }}, $event.target.value)" class="text-[12px] rounded-sm border border-hairline-input px-2 py-1">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($user->roles->pluck('name')->contains($value))>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-ink-mute">No staff accounts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
