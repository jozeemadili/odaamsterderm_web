<x-ui.card padding="p-0">
    <table class="w-full text-[13px]">
        <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
            <tr>
                <th class="px-5 py-3 font-normal">Business</th>
                <th class="px-5 py-3 font-normal">Owner</th>
                <th class="px-5 py-3 font-normal">Status</th>
                <th class="px-5 py-3 font-normal">Documents submitted</th>
                <th class="px-5 py-3 font-normal">Registered</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-hairline">
            @forelse($queue as $merchant)
                <tr class="hover:bg-canvas-soft/60">
                    <td class="px-5 py-3 text-ink font-medium">{{ $merchant->business_name }}</td>
                    <td class="px-5 py-3 text-ink-secondary">{{ $merchant->owner_name }}</td>
                    <td class="px-5 py-3">
                        <x-ui.badge tone="warning">{{ str_replace('_', ' ', $merchant->kyc_status) }}</x-ui.badge>
                    </td>
                    <td class="px-5 py-3 text-ink-secondary tnum">{{ $merchant->kyc_documents_count }}</td>
                    <td class="px-5 py-3 text-ink-mute">{{ $merchant->created_at->diffForHumans() }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.merchants.show', $merchant) }}" class="text-primary hover:text-primary-deep">Review &rarr;</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">Nothing waiting for review.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ui.card>
