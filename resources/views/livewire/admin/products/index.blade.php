<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach(['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <button wire:click="$set('status', '{{ $key }}')"
                    class="text-[12px] px-3 py-1 rounded-pill border {{ $status === $key ? 'bg-brand-dark text-white border-brand-dark' : 'border-hairline text-ink-secondary hover:border-primary/40' }}">
                    {{ $label }}
                    @if($key !== 'all')({{ $counts[$key] ?? 0 }})@endif
                </button>
            @endforeach
        </div>
        <x-ui.button wire:click="startCreating">+ Add catalog product</x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <h2 class="text-[15px] text-ink mb-4">{{ $form->product ? 'Edit '.$form->product->displayName() : 'New catalog product' }}</h2>
            <form wire:submit="save" class="space-y-4">
                @include('livewire.shared.product-form-fields')
                <div class="flex gap-2">
                    <x-ui.button type="submit" target="save">{{ $form->product ? 'Save changes' : 'Add to catalog' }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="$set('showForm', false)">Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search name or brand…" id="catalog_search" />
        <x-ui.select wire:model.live="animalFilter" id="catalog_animal">
            <option value="">All animals</option>
            @foreach($animalGroups as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
                @foreach($group->children as $child)
                    <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
                @endforeach
            @endforeach
        </x-ui.select>
        <x-ui.select wire:model.live="categoryFilter" id="catalog_category">
            <option value="">All categories</option>
            @foreach($filterCategories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
                @foreach($category->children as $child)
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
                    <th class="px-5 py-3 font-normal">Sellers</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($products as $product)
                    <tr class="hover:bg-canvas-soft/60 align-top">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                @if($product->image())
                                    <img src="{{ $product->image()->getUrl() }}" alt="" class="h-8 w-8 rounded object-cover border border-hairline">
                                @else
                                    <span class="h-8 w-8 rounded bg-canvas-soft border border-hairline flex items-center justify-center text-ink-mute text-[10px]">—</span>
                                @endif
                                <div>
                                    <p class="text-ink font-medium">{{ $product->displayName() }}</p>
                                    <p class="text-[11px] text-ink-mute">
                                        {{ $product->makerName() ? 'by '.$product->makerName() : 'No producer' }}@if($product->brand && ! $product->producer_id) (not linked)@endif
                                        @if($product->proposedBy) · proposed by {{ $product->proposedBy->business_name }} @endif
                                    </p>
                                    @if($product->review_notes && $product->status === 'rejected')
                                        <p class="text-[11px] text-ruby mt-0.5">{{ $product->review_notes }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->animalType?->fullName() }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->category?->fullName() }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $product->unit }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $product->sellers_count }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$product->status] ?? 'neutral'">{{ ucfirst($product->status) }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button wire:click="startEditing({{ $product->id }})" class="text-[12px] text-ink-mute hover:text-ink mr-3">Edit</button>
                            @if($product->status !== 'approved')
                                <button wire:click="approve({{ $product->id }})" class="text-[12px] text-primary hover:opacity-80 mr-3">Approve</button>
                            @endif
                            @if($product->status === 'pending')
                                <button wire:click="startReview({{ $product->id }})" class="text-[12px] text-ruby hover:opacity-80">Reject</button>
                            @endif
                        </td>
                    </tr>
                    @if($reviewingId === $product->id)
                        <tr class="bg-canvas-soft/60">
                            <td colspan="7" class="px-5 py-4">
                                <form wire:submit="reject" class="flex flex-wrap gap-3 items-end">
                                    <div class="flex-1 min-w-[260px]">
                                        <x-ui.input wire:model="reviewNotes" label="Reason for rejection (sent to the seller)" id="review_notes" placeholder="e.g. Duplicate of Chicken Starter 25KG" :error="$errors->first('reviewNotes')" />
                                    </div>
                                    <x-ui.button type="submit" variant="danger" size="sm" target="reject">Reject product</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" wire:click="$set('reviewingId', null)">Cancel</x-ui.button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </x-ui.card>

    {{ $products->links() }}
</div>
