<div class="space-y-4">
    <div>
        <x-ui.button wire:click="$toggle('showForm')">
            {{ $showForm ? 'Cancel' : '+ Add business type' }}
        </x-ui.button>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">
            {{ session('status') }}
        </div>
    @endif

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="flex items-end gap-4">
                <div class="flex-1">
                    <x-ui.input wire:model="name" label="Name" id="business_type_name" placeholder="e.g. Duka, Pharmacy, Restaurant" />
                </div>
                <x-ui.button type="submit" target="create">Add</x-ui.button>
            </form>
            @error('name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Name</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($types as $type)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">
                            @if($editingId === $type->id)
                                <form wire:submit="saveEditing" class="flex items-center gap-2">
                                    <input wire:model="editingName" class="rounded-sm border border-hairline-input px-2 py-1 text-[13px]" autofocus>
                                    <button type="submit" class="text-primary text-[12px] font-medium">Save</button>
                                    <button type="button" wire:click="cancelEditing" class="text-ink-mute text-[12px]">Cancel</button>
                                </form>
                                @error('editingName') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                            @else
                                {{ $type->name }}
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$type->is_active ? 'success' : 'neutral'">
                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                            </x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if($editingId !== $type->id)
                                <button wire:click="startEditing({{ $type->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">Rename</button>
                            @endif
                            <button
                                wire:click="toggleActive({{ $type->id }})"
                                wire:confirm="{{ $type->is_active ? 'Deactivate this business type? It will no longer be selectable for new records — existing merchants keep it.' : 'Reactivate this business type?' }}"
                                class="text-[12px] {{ $type->is_active ? 'text-ruby' : 'text-primary' }} hover:opacity-80"
                            >
                                {{ $type->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-ink-mute">No business types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
