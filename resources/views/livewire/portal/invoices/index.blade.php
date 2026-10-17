<div class="space-y-4">
    <x-ui.button wire:click="$toggle('showForm')">
        {{ $showForm ? 'Cancel' : '+ New invoice' }}
    </x-ui.button>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <x-ui.select wire:model="customer_id" label="Customer" id="inv_customer">
                    <option value="">Select customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input type="date" wire:model="issue_date" label="Issue date" id="inv_issue_date" />
                <x-ui.input type="date" wire:model="due_date" label="Due date (optional)" id="inv_due_date" />
                <x-ui.button type="submit" target="create">Create proforma</x-ui.button>
            </form>
            @error('customer_id') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            @error('due_date') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
            <p class="text-[12px] text-ink-mute mt-3">You'll add products and pricing on the next screen. New invoices start as an editable proforma — approve it once your customer confirms the details.</p>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[12px] text-ink-mute mb-1">Status</label>
                <select wire:model.live="statusFilter" class="rounded-sm border border-hairline-input bg-canvas text-[13px] px-2.5 py-1.5">
                    <option value="">All</option>
                    <option value="draft">Proforma</option>
                    <option value="invoiced">Invoiced</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-[12px] text-ink-mute mb-1">From</label>
                <input type="date" wire:model.live="dateFrom" class="rounded-sm border border-hairline-input bg-canvas text-[13px] px-2.5 py-1.5">
            </div>
            <div>
                <label class="block text-[12px] text-ink-mute mb-1">To</label>
                <input type="date" wire:model.live="dateTo" class="rounded-sm border border-hairline-input bg-canvas text-[13px] px-2.5 py-1.5">
            </div>
        </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Number</th>
                    <th class="px-5 py-3 font-normal">Customer</th>
                    <th class="px-5 py-3 font-normal">Issue date</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3 font-normal text-right">Total</th>
                    <th class="px-5 py-3 font-normal text-right">Balance due</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($invoices as $invoice)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">{{ $invoice->number }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $invoice->customer->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $invoice->issue_date->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="match($invoice->status) {
                                'paid' => 'success',
                                'partially_paid' => 'warning',
                                'cancelled' => 'danger',
                                'invoiced' => 'primary',
                                default => 'neutral',
                            }">{{ $invoice->statusLabel() }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 tnum text-ink text-right">{{ number_format($invoice->total, 0) }}</td>
                        <td class="px-5 py-3 tnum text-right {{ $invoice->balanceDue() > 0 ? 'text-ruby' : 'text-ink-mute' }}">{{ number_format($invoice->balanceDue(), 0) }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('portal.invoices.show', $invoice) }}" class="text-primary hover:text-primary-deep">View &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $invoices->links() }}</div>
</div>
