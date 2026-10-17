<div class="space-y-4">
    <x-ui.card>
        <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <x-ui.input type="number" step="0.01" wire:model="amount" label="Amount (TZS)" id="payment_amount" />
            <x-ui.input wire:model="payer_name" label="Payer name" id="payer_name" />
            <x-ui.input type="date" wire:model="payment_date" label="Date" id="payment_date" />
            <div class="md:col-span-2">
                <label class="block text-[13px] text-ink-mute mb-1.5">Proof of payment (photo/receipt)</label>
                <input type="file" wire:model="proof" class="w-full text-[13px] text-ink-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-pill file:border-0 file:text-[12px] file:bg-primary-subtle/40 file:text-primary-deep">
                @error('proof') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                <div wire:loading wire:target="proof" class="text-[12px] text-ink-mute mt-1">Uploading…</div>
            </div>
            <x-ui.button type="submit" target="save">Record payment</x-ui.button>
        </form>
        @error('amount') <p class="text-ruby text-[12px] mt-2">{{ $message }}</p> @enderror
    </x-ui.card>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Amount</th>
                    <th class="px-5 py-3 font-normal">Payer</th>
                    <th class="px-5 py-3 font-normal">Proof</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($payments as $payment)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink-secondary">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="px-5 py-3 tnum text-ink font-medium">TZS {{ number_format($payment->amount, 0) }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $payment->payer_name ?: '—' }}</td>
                        <td class="px-5 py-3">
                            @if($payment->proofOfPayment())
                                <a href="{{ $payment->proofOfPayment()->getUrl() }}" target="_blank" class="text-primary hover:text-primary-deep">view</a>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$payment->status === 'verified' ? 'success' : ($payment->status === 'flagged' ? 'danger' : 'warning')">{{ $payment->status }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $payments->links() }}</div>
</div>
