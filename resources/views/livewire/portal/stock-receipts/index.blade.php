<div class="space-y-4">
    <div>
        <x-ui.button wire:click="$toggle('showForm')">
            {{ $showForm ? 'Cancel' : '+ New stock receipt' }}
        </x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <x-ui.select wire:model="supplier_id" label="Supplier (optional)" id="sr_supplier">
                    <option value="">No supplier</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input wire:model="reference_no" label="Reference no." id="sr_ref" />
                <x-ui.input type="date" wire:model="receipt_date" label="Receipt date" id="sr_date" />
                <x-ui.button type="submit" target="create">Start receipt</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Reference</th>
                    <th class="px-5 py-3 font-normal">Supplier</th>
                    <th class="px-5 py-3 font-normal">Items</th>
                    <th class="px-5 py-3 font-normal">Total</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($receipts as $receipt)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink-secondary">{{ $receipt->receipt_date->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $receipt->reference_no ?: '—' }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $receipt->supplier?->name ?: '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $receipt->items_count }}</td>
                        <td class="px-5 py-3 tnum text-ink font-medium">TZS {{ number_format($receipt->total_amount, 0) }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$receipt->status === 'approved' ? 'success' : ($receipt->status === 'rejected' ? 'danger' : 'warning')">{{ $receipt->status }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('portal.stock-receipts.show', $receipt) }}" class="text-primary hover:text-primary-deep">
                                {{ $receipt->status === 'pending' ? 'Continue' : 'View' }} &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No stock receipts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $receipts->links() }}</div>
</div>
