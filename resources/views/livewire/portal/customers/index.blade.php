<div class="space-y-4">
    <div class="flex gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, phone or code" class="rounded-sm border border-hairline-input bg-canvas flex-1 text-[14px] px-3 py-2 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <x-ui.button wire:click="$toggle('showForm')">
            {{ $showForm ? 'Cancel' : '+ New customer' }}
        </x-ui.button>
    </div>

    @if($showForm)
        <x-ui.card>
            <form wire:submit="create" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-ui.input wire:model="name" label="Full name" id="cust_name" />
                    <x-ui.input wire:model="phone" label="Phone" id="cust_phone" />
                    <x-ui.input type="email" wire:model="email" label="Email" id="cust_email" />
                </div>
                @error('name') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input wire:model="address" label="Address" id="cust_address" />
                    <x-ui.input wire:model="tin_number" label="TIN (optional)" id="cust_tin" />
                </div>

                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-[13px] text-ink-secondary">
                        <input type="checkbox" wire:model.live="credit_allowed" class="rounded border-hairline-input text-primary focus:ring-primary">
                        Allow credit sales
                    </label>
                    @if($credit_allowed)
                        <div class="w-48">
                            <x-ui.input type="number" step="0.01" wire:model="credit_limit" label="Credit limit (TZS)" id="cust_credit_limit" />
                        </div>
                    @endif
                </div>

                <x-ui.button type="submit" target="create">Save customer</x-ui.button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Code</th>
                    <th class="px-5 py-3 font-normal">Name</th>
                    <th class="px-5 py-3 font-normal">Phone</th>
                    <th class="px-5 py-3 font-normal">Credit</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($customers as $customer)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 tnum text-ink-mute">{{ $customer->customer_code }}</td>
                        <td class="px-5 py-3 text-ink font-medium">{{ $customer->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary tnum">{{ $customer->phone }}</td>
                        <td class="px-5 py-3">
                            @if($customer->credit_allowed)
                                <x-ui.badge tone="primary">up to TZS {{ number_format($customer->credit_limit, 0) }}</x-ui.badge>
                            @else
                                <span class="text-ink-mute">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('portal.customers.show', $customer) }}" class="text-primary hover:text-primary-deep">View &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-ink-mute">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div>{{ $customers->links() }}</div>
</div>
