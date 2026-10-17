<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">Animal types classify every catalog product and drive marketplace search. Use a group (e.g. Poultry) with animal types under it (e.g. Chicken); filtering by a group also finds its animal types.</p>
        <x-ui.button wire:click="startCreating">+ Add animal type</x-ui.button>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @if($showForm)
        <x-ui.card>
            <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <x-ui.input wire:model="name" label="Name" id="animal_name" placeholder="e.g. Chicken, Ducks, Rabbits" :error="$errors->first('name')" />
                </div>
                <x-ui.select wire:model="parent_id" label="Group" id="animal_parent" :error="$errors->first('parent_id')">
                    <option value="">— Top-level group —</option>
                    @foreach($groups as $group)
                        @if($group->id !== $editingId)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endif
                    @endforeach
                </x-ui.select>
                <x-ui.input type="number" min="0" wire:model="sort_order" label="Sort order" id="animal_sort" :error="$errors->first('sort_order')" />
                <div class="md:col-span-4 flex gap-2">
                    <x-ui.button type="submit" target="save">{{ $editingId ? 'Save changes' : 'Add' }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[720px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Animal type</th>
                    <th class="px-5 py-3 font-normal">Products</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($groups as $group)
                    @foreach([$group, ...$group->children] as $type)
                        <tr class="hover:bg-canvas-soft/60">
                            <td class="px-5 py-3 {{ $type->parent_id ? 'pl-10 text-ink-secondary' : 'text-ink font-medium' }}">
                                {{ $type->parent_id ? '↳ ' : '' }}{{ $type->name }}
                            </td>
                            <td class="px-5 py-3 tnum text-ink-secondary">{{ $type->products_count }}</td>
                            <td class="px-5 py-3">
                                <x-ui.badge :tone="$type->is_active ? 'success' : 'neutral'">{{ $type->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @unless($type->parent_id)
                                    <button wire:click="startCreating({{ $type->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">+ Sub-type</button>
                                @endunless
                                <button wire:click="startEditing({{ $type->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">Edit</button>
                                <button wire:click="toggleActive({{ $type->id }})"
                                    wire:confirm="{{ $type->is_active ? 'Deactivate this animal type?' : 'Reactivate this animal type?' }}"
                                    class="text-[12px] {{ $type->is_active ? 'text-ruby' : 'text-primary' }} hover:opacity-80">
                                    {{ $type->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-ink-mute">No animal types yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>
</div>
