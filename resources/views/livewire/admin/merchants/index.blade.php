<div class="space-y-4">
    <div class="flex gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by business or owner name" class="rounded-sm border border-hairline-input bg-canvas flex-1 text-[14px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <select wire:model.live="kycFilter" class="rounded-sm border border-hairline-input bg-canvas text-[14px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            <option value="">All KYC statuses</option>
            <option value="pending">Pending</option>
            <option value="under_review">Under review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
        <a href="{{ route('admin.merchants.create') }}" class="inline-flex items-center justify-center gap-2 rounded-pill font-normal leading-none transition bg-primary text-white hover:bg-primary-deep px-4 py-2 text-[15px] whitespace-nowrap">+ New merchant</a>
    </div>

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Business</th>
                    <th class="px-5 py-3 font-normal">Owner</th>
                    <th class="px-5 py-3 font-normal">Region</th>
                    <th class="px-5 py-3 font-normal">KYC status</th>
                    <th class="px-5 py-3 font-normal">Account</th>
                    <th class="px-5 py-3 font-normal">Tier</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($merchants as $merchant)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium">{{ $merchant->business_name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $merchant->owner_name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $merchant->region }}</td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$merchant->kyc_status === 'approved' ? 'success' : ($merchant->kyc_status === 'rejected' ? 'danger' : 'warning')">
                                {{ str_replace('_', ' ', $merchant->kyc_status) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-5 py-3">
                            <x-ui.badge :tone="$merchant->status === 'active' ? 'success' : 'danger'">{{ $merchant->status }}</x-ui.badge>
                        </td>
                        <td class="px-5 py-3 text-ink-secondary capitalize">{{ $merchant->subscription_tier }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.merchants.show', $merchant) }}" class="text-primary hover:text-primary-deep">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-ink-mute">No merchants found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $merchants->links() }}</div>
</div>
