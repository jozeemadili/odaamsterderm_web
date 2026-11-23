{{-- Add / edit an animal. Expects $groups (top-level animals) and the component's form fields. --}}
<x-ui.card>
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-[15px] font-semibold text-ink">{{ $editingId ? 'Edit animal' : ($parent_id ? 'New type under '.($groups->firstWhere('id', (int) $parent_id)?->name ?? 'group') : 'New animal group') }}</h2>
        <button type="button" wire:click="$set('showForm', false)" class="text-[13px] text-ink-mute hover:text-ink">Close</button>
    </div>
    <form wire:submit="save" class="grid grid-cols-1 gap-5 md:grid-cols-[160px_1fr]">
        <div>
            @php($current = $editingId ? \App\Models\AnimalType::find($editingId) : null)
            <label for="animal_image" class="flex h-36 w-36 cursor-pointer items-center justify-center overflow-hidden rounded-[20px] border border-dashed border-hairline bg-canvas-soft hover:border-primary/50">
                @if($image && method_exists($image, 'temporaryUrl'))
                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                @elseif($current?->imageUrl())
                    <img src="{{ $current->imageUrl() }}" alt="" class="h-full w-full object-cover">
                @else
                    <span class="px-3 text-center text-[12px] text-ink-mute"><span class="block text-[34px]" aria-hidden="true">{{ $current?->emoji() ?? '🐾' }}</span>Add a photo</span>
                @endif
            </label>
            <input type="file" wire:model="image" id="animal_image" accept="image/*" class="sr-only">
            <span wire:loading wire:target="image" class="mt-1 block text-[11.5px] text-ink-mute">Uploading…</span>
            @error('image') <p class="mt-1 text-[12px] text-ruby">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-ui.input wire:model="name" label="Name" id="animal_name" placeholder="e.g. Chicken, Ducks, Rabbits" :error="$errors->first('name')" />
            <x-ui.input wire:model="name_sw" label="Name (Kiswahili)" id="animal_name_sw" placeholder="e.g. Kuku, Bata, Sungura" :error="$errors->first('name_sw')" />
            <x-ui.select wire:model="parent_id" label="Group" id="animal_parent" :error="$errors->first('parent_id')">
                <option value="">— This is a group —</option>
                @foreach($groups as $group)
                    @if($group->id !== $editingId)<option value="{{ $group->id }}">{{ $group->name }}</option>@endif
                @endforeach
            </x-ui.select>
            <div class="md:col-span-2">
                <x-ui.textarea wire:model="description" label="Description (optional)" id="animal_description" rows="2" :error="$errors->first('description')"></x-ui.textarea>
            </div>
            <x-ui.input type="number" min="0" wire:model="sort_order" label="Sort order" id="animal_sort" :error="$errors->first('sort_order')" />
            <div class="flex gap-2 md:col-span-3">
                <x-ui.button type="submit" target="save">{{ $editingId ? 'Save changes' : 'Add' }}</x-ui.button>
                <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
            </div>
        </div>
    </form>
</x-ui.card>
