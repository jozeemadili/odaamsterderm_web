<div class="space-y-4">
    <x-ui.card>
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-[15px] text-ink-secondary">Merchant profile</h2>
                <x-ui.badge :tone="$merchant->status === 'active' ? 'success' : 'danger'">{{ $merchant->status }}</x-ui.badge>
            </div>
            <div class="flex gap-2">
                @if(! $isEditing)
                    <x-ui.button size="sm" variant="secondary" wire:click="startEditing">Edit profile</x-ui.button>
                @endif
                <x-ui.button
                    size="sm"
                    :variant="$merchant->status === 'active' ? 'danger' : 'primary'"
                    wire:click="toggleStatus"
                    target="toggleStatus"
                    wire:confirm="{{ $merchant->status === 'active' ? 'Deactivate this merchant? Their users will be signed out and unable to log in.' : 'Reactivate this merchant?' }}"
                >
                    {{ $merchant->status === 'active' ? 'Deactivate' : 'Activate' }}
                </x-ui.button>
            </div>
        </div>

        @if($isEditing)
            <form wire:submit="updateProfile" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input wire:model="business_name" label="Business name" id="edit_business_name" />
                    <x-ui.input wire:model="owner_name" label="Owner name" id="edit_owner_name" />
                </div>
                @error('business_name') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                @error('owner_name') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ui.input wire:model="phone" label="Phone" id="edit_phone" />
                    <x-ui.input type="email" wire:model="email" label="Email" id="edit_email" />
                </div>
                @error('phone') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
                @error('email') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-ui.select wire:model="business_type_id" label="Business type" id="edit_business_type_id">
                        <option value="">Select business type</option>
                        @foreach($businessTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select wire:model="region_id" label="Region" id="edit_region_id">
                        <option value="">Select region</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input wire:model="city" label="City" id="edit_city" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-ui.input wire:model="tin_number" label="TIN" id="edit_tin" />
                    <x-ui.input wire:model="physical_address" label="Physical address" id="edit_address" />
                    <x-ui.select wire:model="subscription_tier" label="Subscription tier" id="edit_tier">
                        <option value="basic">Basic</option>
                        <option value="business">Business</option>
                        <option value="professional">Professional</option>
                    </x-ui.select>
                </div>

                <div class="flex gap-2">
                    <x-ui.button type="submit" target="updateProfile">Save changes</x-ui.button>
                    <x-ui.button type="button" variant="ghost" wire:click="cancelEditing">Cancel</x-ui.button>
                </div>
            </form>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5 text-[13px]">
                <div><p class="text-ink-mute mb-1">Business</p><p class="text-ink font-medium">{{ $merchant->business_name }}</p></div>
                <div><p class="text-ink-mute mb-1">Owner</p><p class="text-ink font-medium">{{ $merchant->owner_name }}</p></div>
                <div><p class="text-ink-mute mb-1">Phone</p><p class="text-ink font-medium tnum">{{ $merchant->phone }}</p></div>
                <div><p class="text-ink-mute mb-1">Email</p><p class="text-ink font-medium">{{ $merchant->email ?: '—' }}</p></div>
                <div><p class="text-ink-mute mb-1">Business type</p><p class="text-ink font-medium">{{ $merchant->businessTypeRef?->name ?? $merchant->business_type ?? '—' }}</p></div>
                <div><p class="text-ink-mute mb-1">Region / City</p><p class="text-ink font-medium">{{ $merchant->regionRef?->name ?? $merchant->region }} {{ $merchant->city }}</p></div>
                <div><p class="text-ink-mute mb-1">TIN</p><p class="text-ink font-medium tnum">{{ $merchant->tin_number ?: '—' }}</p></div>
                <div><p class="text-ink-mute mb-1">Subscription tier</p><p class="text-ink font-medium capitalize">{{ $merchant->subscription_tier }}</p></div>
            </div>
        @endif
    </x-ui.card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total recorded sales" value="TZS {{ number_format($salesTotal, 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Total recorded expenses" value="TZS {{ number_format($expensesTotal, 0) }}" tone="ruby" />
        </x-ui.card>
    </div>

    <x-ui.card>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[15px] text-ink-secondary">KYC Review</h2>
            <x-ui.badge :tone="$merchant->kyc_status === 'approved' ? 'success' : ($merchant->kyc_status === 'rejected' ? 'danger' : 'warning')">
                {{ str_replace('_', ' ', $merchant->kyc_status) }}
            </x-ui.badge>
        </div>

        <h3 class="text-[13px] text-ink-mute mb-2">Submitted documents</h3>
        <div class="space-y-2 mb-5">
            @forelse($documents as $doc)
                <div class="flex items-center justify-between border border-hairline rounded-md px-3.5 py-2.5 text-[13px]">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-ink capitalize">{{ str_replace('_', ' ', $doc->document_type) }}</span>
                        <x-ui.badge :tone="$doc->status === 'approved' ? 'success' : ($doc->status === 'rejected' ? 'danger' : 'neutral')">{{ $doc->status }}</x-ui.badge>
                        @if($doc->file())
                            <a href="{{ $doc->file()->getUrl() }}" target="_blank" class="text-primary hover:text-primary-deep">view file</a>
                        @endif
                    </div>
                    @if($doc->status === 'pending')
                        <div class="flex gap-2">
                            <x-ui.button size="sm" variant="secondary" wire:click="approveDocument({{ $doc->id }})" target="approveDocument({{ $doc->id }})">Approve</x-ui.button>
                            <x-ui.button size="sm" variant="danger" wire:click="rejectDocument({{ $doc->id }})" target="rejectDocument({{ $doc->id }})">Reject</x-ui.button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-[13px] text-ink-mute">No documents submitted yet.</p>
            @endforelse
        </div>

        <x-ui.textarea wire:model="reviewNotes" label="Review notes" rows="2" class="mb-4"></x-ui.textarea>

        <div class="flex gap-2">
            <x-ui.button variant="secondary" wire:click="markUnderReview" target="markUnderReview">Mark under review</x-ui.button>
            <x-ui.button variant="primary" wire:click="approveKyc" target="approveKyc">Approve merchant</x-ui.button>
            <x-ui.button variant="danger" wire:click="rejectKyc" target="rejectKyc">Reject merchant</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Shop users</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Name</th>
                    <th class="px-5 py-3 font-normal">Email</th>
                    <th class="px-5 py-3 font-normal">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($users as $user)
                    <tr>
                        <td class="px-5 py-3 text-ink font-medium">{{ $user->name }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ $user->email }}</td>
                        <td class="px-5 py-3 text-ink-secondary">{{ str_replace('merchant_', '', $user->roles->first()?->name ?? '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-6 text-center text-ink-mute">No users yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
