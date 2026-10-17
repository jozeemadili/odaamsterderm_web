<div class="space-y-4">
    <div>
        <select wire:model.live="statusFilter" class="rounded-sm border border-hairline-input bg-canvas text-[14px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            <option value="">All statuses</option>
            <option value="recorded">Awaiting verification</option>
            <option value="verified">Verified</option>
            <option value="flagged">Flagged</option>
        </select>
    </div>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Merchant</th>
                    <th class="px-5 py-3 font-normal">Amount</th>
                    <th class="px-5 py-3 font-normal">Payer</th>
                    <th class="px-5 py-3 font-normal">Date</th>
                    <th class="px-5 py-3 font-normal">Proof</th>
                    <th class="px-5 py-3 font-normal">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($payments as $payment)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">{{ $payment->merchant->business_name }}</td>
                        <td class="px-5 py-3 tnum text-ink">TZS {{ number_format($payment->amount, 0) }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $payment->payer_name ?: '—' }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            @if($payment->proofOfPayment())
                                <a href="{{ $payment->proofOfPayment()->getUrl() }}" target="_blank" class="text-primary hover:text-primary-deep">view</a>
                            @else
                                <span class="text-ruby text-[12px]">missing</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$payment->status === 'verified' ? 'success' : ($payment->status === 'flagged' ? 'danger' : 'warning')">{{ $payment->status }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            @if($payment->status !== 'verified')
                                <x-ui.button size="sm" variant="secondary" wire:click="verify({{ $payment->id }})" target="verify({{ $payment->id }})" class="mr-2">Verify</x-ui.button>
                            @endif
                            @if($payment->status !== 'flagged')
                                <x-ui.button size="sm" variant="danger" wire:click="flag({{ $payment->id }})" target="flag({{ $payment->id }})">Flag</x-ui.button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No payments in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $payments->links() }}</div>
</div>
