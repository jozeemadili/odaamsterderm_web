<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-2xl">The shared catalog lets customers compare the same product across sellers. Link your inventory items to catalog products to sell them on the marketplace.</p>
        <x-ui.button wire:click="$toggle('showForm')">{{ $showForm ? 'Cancel' : '+ Propose a product' }}</x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <h2 class="text-[15px] text-ink mb-1">Propose a new catalog product</h2>
            <p class="text-[12px] text-ink-mute mb-4">Search the catalog first to avoid duplicates. An admin reviews every proposal.</p>
            <form wire:submit="propose" class="space-y-4">
                @include('livewire.shared.product-form-fields')
                <x-ui.button type="submit" target="propose">Submit for review</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    @if($proposals->isNotEmpty())
        <x-ui.card padding="p-0">
            <div class="px-5 py-3 border-b border-hairline"><h2 class="text-[14px] text-ink-secondary">Your proposals</h2></div>
            <div class="divide-y divide-hairline">
                @foreach($proposals as $proposal)
                    <div class="px-5 py-3 flex items-center justify-between gap-3 text-[13px]">
                        <div>
                            <p class="text-ink">{{ $proposal->displayName() }}</p>
                            @if($proposal->status === 'rejected' && $proposal->review_notes)
                                <p class="text-[12px] text-ruby">Rejected: {{ $proposal->review_notes }}</p>
                            @endif
                        </div>
                        <x-ui.badge :tone="$proposal->status === 'pending' ? 'warning' : 'danger'">{{ $proposal->status === 'pending' ? 'Waiting for review' : 'Rejected' }}</x-ui.badge>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search name or brand…" id="seller_catalog_search" />
        <x-ui.select wire:model.live="animalFilter" id="seller_catalog_animal">
            <option value="">All animals</option>
            @foreach($animalGroups as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
                @foreach($group->children as $child)
                    <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
                @endforeach
            @endforeach
        </x-ui.select>
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
        <table class="w-full text-[13px] min-w-[720px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Product</th>
                    <th class="px-5 py-3 font-normal">Animal</th>
                    <th class="px-5 py-3 font-normal">Category</th>
                    <th class="px-5 py-3 font-normal">Unit</th>
                    <th class="px-5 py-3 font-normal">In your inventory</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($products as $product)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3">
                            <p class="text-ink font-medium">{{ $product->displayName() }}</p>
                            @if($product->brand)<p class="text-[11px] text-ink-mute">{{ $product->brand }}</p>@endif
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->animalType?->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->category?->fullName() }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->unit }}</td>
                        <td class="px-5 py-3">
                            @if($product->stocked_count > 0)
                                <x-ui.badge tone="success">{{ $product->stocked_count }} item{{ $product->stocked_count > 1 ? 's' : '' }}</x-ui.badge>
                            @else
                                <span class="text-ink-mute text-[12px]">Not stocked</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No catalog products match.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $products->links() }}
</div>
