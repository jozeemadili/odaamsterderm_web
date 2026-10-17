<div class="space-y-4">
    <div>
        <x-ui.button wire:click="$toggle('showForm')">
            {{ $showForm ? 'Cancel' : '+ New store' }}
        </x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <x-ui.input wire:model="name" label="Store name" id="store_name" />
                <x-ui.input wire:model="address" label="Address" id="store_address" />
                <x-ui.input wire:model="phone" label="Phone" id="store_phone" />
                <x-ui.button type="submit" target="create">Save store</x-ui.button>
            </form>
            @error('name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            <label class="flex items-center gap-2 text-[13px] text-ink-secondary mt-3">
                <input type="checkbox" wire:model="is_primary" class="rounded border-hairline-input text-primary focus:ring-primary">
                Set as primary store
            </label>
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($stores as $store)
            <a href="{{ route('portal.stores.show', $store) }}" class="block">
                <x-ui.card>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-[15px] text-ink font-medium">{{ $store->name }}</h3>
                        @if($store->is_primary)
                            <x-ui.badge tone="primary">primary</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-[13px] text-ink-mute">{{ $store->address ?: 'No address on file' }}</p>
                    <p class="text-[13px] text-ink-mute">{{ $store->phone }}</p>
                    <p class="text-[12px] text-primary mt-3">{{ $store->inventory_items_count }} products &rarr;</p>
                </x-ui.card>
            </a>
        @empty
            <p class="text-[13px] text-ink-mute col-span-full">No stores yet. Add one to start assigning products to locations.</p>
        @endforelse
    </div>
</div>
