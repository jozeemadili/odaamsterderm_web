<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <p class="text-[13px] text-ink-mute max-w-2xl">Platform-wide categories for catalog products (Feed › Starter, Vaccines, Equipment…). Limit a top-level category to certain animal groups to keep seller and customer choices relevant — leave it open to allow every animal.</p>
        <x-ui.button wire:click="startCreating">+ Add category</x-ui.button>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    @if($showForm)
        <x-ui.card>
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="name" label="Name" id="category_name" placeholder="e.g. Feed, Starter, Dewormers" :error="$errors->first('name')" />
                    </div>
                    <x-ui.select wire:model.live="parent_id" label="Parent category" id="category_parent" :error="$errors->first('parent_id')">
                        <option value="">— Top-level category —</option>
                        @foreach($categories as $category)
                            @if($category->id !== $editingId)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endif
                        @endforeach
                    </x-ui.select>
                    <x-ui.input type="number" min="0" wire:model="sort_order" label="Sort order" id="category_sort" :error="$errors->first('sort_order')" />
                </div>

                @if($parent_id === '')
                    <div>
                        <p class="text-[13px] text-ink-mute mb-2">Applies to animal groups <span class="text-[12px]">(none selected = all animals)</span></p>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach($animalGroups as $group)
                                <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary">
                                    <input type="checkbox" wire:model="animalTypeIds" value="{{ $group->id }}" class="rounded border-hairline-input text-primary focus:ring-primary">
                                    {{ $group->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-[12px] text-ink-mute">Sub-categories follow their parent's animal groups.</p>
                @endif

                <div class="flex gap-2">
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
                    <th class="px-5 py-3 font-normal">Category</th>
                    <th class="px-5 py-3 font-normal">Animal groups</th>
                    <th class="px-5 py-3 font-normal">Products</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($categories as $parent)
                    @foreach([$parent, ...$parent->children] as $category)
                        <tr class="hover:bg-canvas-soft/60">
                            <td class="px-5 py-3 {{ $category->parent_id ? 'pl-10 text-ink-secondary' : 'text-ink font-medium' }}">
                                {{ $category->parent_id ? '↳ ' : '' }}{{ $category->name }}
                            </td>
                            <td class="px-5 py-3 text-ink-mute text-[12px]">
                                @if($category->parent_id)
                                    <span class="italic">as parent</span>
                                @else
                                    {{ $category->animalTypes->pluck('name')->join(', ') ?: 'All animals' }}
                                @endif
                            </td>
                            <td class="px-5 py-3 tnum text-ink-secondary">{{ $category->products_count }}</td>
                            <td class="px-5 py-3">
                                <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-ui.badge>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @unless($category->parent_id)
                                    <button wire:click="startCreating({{ $category->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">+ Sub-category</button>
                                @endunless
                                <button wire:click="startEditing({{ $category->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">Edit</button>
                                <button wire:click="toggleActive({{ $category->id }})"
                                    wire:confirm="{{ $category->is_active ? 'Deactivate this category?' : 'Reactivate this category?' }}"
                                    class="text-[12px] {{ $category->is_active ? 'text-ruby' : 'text-primary' }} hover:opacity-80">
                                    {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>
</div>
