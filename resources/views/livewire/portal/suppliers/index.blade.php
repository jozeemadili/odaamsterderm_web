<div class="space-y-4">
    <div>
        <x-ui.button wire:click="$toggle('showSupplierForm')">
            {{ $showSupplierForm ? 'Cancel' : '+ Add supplier' }}
        </x-ui.button>
    </div>

    @if($showSupplierForm)
        <x-ui.card>
            <form wire:submit="addSupplier" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <x-ui.input wire:model="name" label="Supplier name" id="supplier_name" />
                <x-ui.input wire:model="contact_person" label="Contact person" id="supplier_contact" />
                <x-ui.input wire:model="phone" label="Phone" id="supplier_phone" />
                <x-ui.button type="submit" target="addSupplier">Save supplier</x-ui.button>
            </form>
            @error('name') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Supplier</th>
                    <th class="px-5 py-3 font-normal">Contact</th>
                    <th class="px-5 py-3 font-normal">Outstanding balance</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($suppliers as $supplier)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">{{ $supplier->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $supplier->contact_person }} {{ $supplier->phone }}</td>
                        <td class="px-5 py-3 tnum text-ink">TZS {{ number_format($supplier->outstandingBalance(), 0) }}</td>
                        <td class="px-5 py-3 text-right">
                            <x-ui.button size="sm" variant="secondary" wire:click="startTransaction({{ $supplier->id }})" target="startTransaction({{ $supplier->id }})">Record purchase / payment</x-ui.button>
                        </td>
                    </tr>

                    @if($transactingSupplierId === $supplier->id)
                        <tr class="bg-canvas-soft/60">
                            <td colspan="4" class="px-5 py-4">
                                <form wire:submit="saveTransaction" class="flex flex-wrap gap-4 items-end">
                                    <x-ui.select wire:model="transactionType" label="Type" id="txn_type">
                                        <option value="purchase">Purchase (increases balance)</option>
                                        <option value="payment">Payment (reduces balance)</option>
                                    </x-ui.select>
                                    <x-ui.input type="number" step="0.01" wire:model="transactionAmount" label="Amount (TZS)" id="txn_amount" />
                                    <x-ui.input type="date" wire:model="transactionDate" label="Date" id="txn_date" />
                                    <x-ui.input wire:model="transactionDescription" label="Note" id="txn_note" />
                                    <x-ui.button type="submit" variant="primary" size="sm" target="saveTransaction">Save</x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" wire:click="$set('transactingSupplierId', null)">Cancel</x-ui.button>
                                </form>
                                @error('transactionAmount') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-ink-mute">No suppliers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
