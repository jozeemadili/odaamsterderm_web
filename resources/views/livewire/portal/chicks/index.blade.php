<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-3xl">Sell day-old chicks from the hatcheries you work with. Add a contract for each hatchery and chick type: the days the chicks arrive at your stores (Monday and Thursday by default) and how many boxes of {{ $boxSize }} you can take each day. Customers book until a day is full or has passed; bookings arrive in <a href="{{ route('portal.orders.index') }}" class="text-primary hover:text-primary-deep">Orders</a>.</p>
        <div class="flex items-center gap-1 rounded-pill border border-hairline bg-canvas p-1 text-[13px]">
            <button wire:click="$set('tab', 'days')" class="px-3 py-1.5 rounded-pill {{ $tab === 'days' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Order days</button>
            <button wire:click="$set('tab', 'contracts')" class="px-3 py-1.5 rounded-pill {{ $tab === 'contracts' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Hatchery contracts ({{ $contracts->count() }})</button>
        </div>
    </div>

    @if($agents->isNotEmpty())
        <div class="max-w-sm">
            <x-ui.select wire:model.live="agent" label="Agent" id="chk_agent">
                @foreach($agents as $a)<option value="{{ $a->id }}">{{ $a->business_name }}</option>@endforeach
            </x-ui.select>
        </div>
    @endif

    @if($addons && $addons['bookings'] > 0)
        <p class="text-[13px] text-ink-secondary">Last 30 days: <strong class="tnum">{{ $addons['bookings'] }}</strong> chick {{ \Illuminate\Support\Str::plural('booking', $addons['bookings']) }}, <strong class="tnum">{{ $addons['with_addons'] }}</strong> ({{ $addons['rate'] }}%) added suggested feed, vaccines or vitamins @if($addons['value'] > 0), worth <strong class="tnum">TZS {{ number_format($addons['value']) }}</strong>@endif.</p>
    @endif

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-ruby/10 border border-ruby/20 text-ruby px-4 py-2.5 text-[14px]">{{ session('error') }}</div>
    @endif

    @if($tab === 'contracts')
        <div class="flex justify-end">
            <x-ui.button wire:click="newContract">+ Add hatchery contract</x-ui.button>
        </div>

        @if($showContractForm)
            <x-ui.card>
                <h2 class="text-[15px] font-medium mb-3">{{ $editingContractId ? 'Edit contract' : 'New hatchery contract' }}</h2>
                <form wire:submit="saveContract" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <x-ui.select wire:model.live="producer_id" label="Hatchery" id="cc_producer" :error="$errors->first('producer_id')">
                            <option value="">Choose…</option>
                            @foreach($hatcheries as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                            <option value="new">+ Not listed: add a new hatchery</option>
                        </x-ui.select>
                        @if($producer_id === 'new')
                            <x-ui.input wire:model="new_hatchery" label="New hatchery name" id="cc_new_hatchery" placeholder="e.g. Kuku Bora Hatchery" />
                        @endif
                        <x-ui.select wire:model="chick_type_id" label="Chick type" id="cc_type" :error="$errors->first('chick_type_id')">
                            <option value="">Choose…</option>
                            @foreach($chickTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </x-ui.select>
                    </div>

                    <div>
                        <p class="text-[13px] text-ink-mute mb-1.5">Days the chicks arrive at your stores</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($weekdayNames as $n => $label)
                                <label class="inline-flex items-center gap-1.5 rounded-pill border border-hairline px-3 py-1.5 text-[13px] has-[:checked]:border-primary has-[:checked]:bg-primary-subtle">
                                    <input type="checkbox" wire:model="weekdays" value="{{ $n }}" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('weekdays') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <p class="text-[13px] text-ink-mute mb-1.5">Stores the chicks arrive at <span class="text-ink-mute/80">(none ticked = all your marketplace stores)</span></p>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            @foreach($stores as $s)
                                <label class="flex items-center gap-2 text-[14px] {{ $s->is_marketplace_enabled ? '' : 'text-ink-mute' }}">
                                    <input type="checkbox" wire:model="branch_ids" value="{{ $s->id }}" @disabled(! $s->is_marketplace_enabled) class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $s->name }}{{ $s->is_marketplace_enabled ? '' : ' (not on marketplace)' }}
                                </label>
                            @endforeach
                        </div>
                        @error('branch_ids') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <x-ui.input type="number" min="1" wire:model="cap_boxes" label="Cap per day (boxes of {{ $boxSize }})" id="cc_cap" :error="$errors->first('cap_boxes')" />
                        <x-ui.input type="number" min="0" step="any" wire:model="contract_price" label="Price per chick (TZS)" id="cc_price" :error="$errors->first('contract_price')" />
                        <label class="flex items-center gap-2 text-[14px] pb-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-hairline-input text-primary focus:ring-primary"> Selling now
                        </label>
                    </div>

                    <div class="flex gap-2">
                        <x-ui.button type="submit" target="saveContract">{{ $editingContractId ? 'Save contract' : 'Add contract' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showContractForm', false)">Cancel</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                        <tr>
                            <th class="px-4 py-3 font-normal">Hatchery</th>
                            <th class="px-4 py-3 font-normal">Type</th>
                            <th class="px-4 py-3 font-normal">Arrive</th>
                            <th class="px-4 py-3 font-normal">Stores</th>
                            <th class="px-4 py-3 font-normal text-right">Cap / day</th>
                            <th class="px-4 py-3 font-normal">Price / chick</th>
                            <th class="px-4 py-3 font-normal text-right">Upcoming days</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($contracts as $c)
                            <tr class="hover:bg-canvas-soft/60 {{ $c->is_active ? '' : 'text-ink-mute' }}" wire:key="cc-{{ $c->id }}">
                                <td class="px-4 py-3 font-medium {{ $c->is_active ? 'text-ink' : '' }}">{{ $c->producer->name }}@if($c->is_sample) <x-ui.badge tone="neutral">sample</x-ui.badge>@endif</td>
                                <td class="px-4 py-3">{{ $c->chickType->name }}</td>
                                <td class="px-4 py-3">{{ $c->weekdayLabels() }}</td>
                                <td class="px-4 py-3">{{ $c->branch_ids ? $stores->whereIn('id', $c->branch_ids)->pluck('name')->join(', ') : 'All marketplace stores' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $c->cap_boxes }} boxes</td>
                                <td class="px-4 py-3 tnum">{{ $c->price_per_chick !== null ? 'TZS '.number_format($c->price_per_chick) : '—' }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $c->upcoming_days }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                    <button wire:click="$set('contract', '{{ $c->id }}'); $set('tab', 'days')" class="text-[12px] text-primary hover:text-primary-deep">Days</button>
                                    <button wire:click="editContract({{ $c->id }})" class="text-[12px] text-ink-secondary hover:text-ink">Edit</button>
                                    <button wire:click="toggleContract({{ $c->id }})" class="text-[12px] text-ink-mute hover:text-ink">{{ $c->is_active ? 'Pause' : 'Resume' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-ink-mute">No hatchery contracts yet. Add the hatcheries you sell chicks for.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @else
        <div class="flex flex-wrap items-end gap-3">
            <x-ui.select wire:model.live="contract" label="Hatchery" id="flt_contract">
                <option value="">All contracts</option>
                @foreach($contracts as $c)<option value="{{ $c->id }}">{{ $c->producer->name }} · {{ $c->chickType->name }}</option>@endforeach
            </x-ui.select>
            <label class="flex items-center gap-2 text-[13px] text-ink-secondary pb-2">
                <input type="checkbox" wire:model.live="showPast" class="rounded border-hairline-input text-primary focus:ring-primary"> Show past days
            </label>
            @if($contracts->isNotEmpty())
                <div class="ml-auto"><x-ui.button wire:click="newDay">+ Extra order day</x-ui.button></div>
            @endif
        </div>

        @if($showDayForm)
            <x-ui.card>
                <h2 class="text-[15px] font-medium mb-3">{{ $editingDayId ? 'Edit order day' : 'Extra order day' }}</h2>
                <form wire:submit="saveDay" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <x-ui.select wire:model.live="day_contract_id" label="Hatchery" id="od_contract" :error="$errors->first('day_contract_id')" :disabled="(bool) $editingDayId">
                        <option value="">Choose…</option>
                        @foreach($contracts->where('is_active', true) as $c)<option value="{{ $c->id }}">{{ $c->producer->name }} · {{ $c->chickType->name }}</option>@endforeach
                    </x-ui.select>
                    <x-ui.input type="date" wire:model="day_date" label="Day the chicks arrive" id="od_date" :error="$errors->first('day_date')" />
                    <x-ui.input type="number" min="1" wire:model="day_cap" label="Cap (boxes of {{ $boxSize }})" id="od_cap" :error="$errors->first('day_cap')" />
                    <x-ui.input type="number" min="0" step="any" wire:model="day_price" label="Price per chick (TZS)" id="od_price" :error="$errors->first('day_price')" />
                    <div class="md:col-span-3">
                        <x-ui.input wire:model="day_notes" label="Note for customers (optional)" id="od_notes" placeholder="e.g. Vaccinated against Marek's and Newcastle" />
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button type="submit" target="saveDay">{{ $editingDayId ? 'Save' : 'Add day' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showDayForm', false)">Cancel</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        @forelse($days as $date => $list)
            <x-ui.card padding="p-0">
                <div class="px-4 py-2.5 border-b border-hairline flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-[15px] font-semibold text-ink">{{ \Illuminate\Support\Carbon::parse($date)->format('l j M Y') }}</h3>
                    <p class="text-[12px] text-ink-mute tnum">{{ $list->sum(fn ($d) => intdiv($d->booked(), $boxSize)) }} of {{ $list->sum(fn ($d) => $d->capBoxes()) }} boxes booked</p>
                </div>
                <div class="divide-y divide-hairline">
                    @foreach($list as $d)
                        @php($status = $d->status())
                        <div class="px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px]" wire:key="od-{{ $d->id }}">
                            <div class="min-w-[180px] flex-1">
                                <p class="font-medium text-ink">{{ $d->producer->name }} · {{ $d->chickType->name }}</p>
                                @if($d->notes)<p class="text-[11.5px] text-ink-mute">{{ $d->notes }}</p>@endif
                            </div>
                            <div class="w-40">
                                @php($pct = $d->capacity ? min(100, round(100 * $d->booked() / $d->capacity)) : 0)
                                <div class="h-1.5 rounded-full bg-hairline overflow-hidden"><div class="h-full bg-primary" style="width: {{ $pct }}%"></div></div>
                                <p class="mt-1 text-[11.5px] text-ink-mute tnum">{{ intdiv($d->booked(), $boxSize) }} / {{ $d->capBoxes() }} boxes · {{ $d->boxesAvailable() }} left</p>
                            </div>
                            <span class="tnum w-28">{{ $d->price_per_chick !== null ? 'TZS '.number_format($d->price_per_chick) : '—' }}</span>
                            <x-ui.badge :tone="match($status) { 'open' => 'success', 'full' => 'warning', default => 'neutral' }">{{ $d->statusLabel() }}</x-ui.badge>
                            <div class="ml-auto whitespace-nowrap space-x-2">
                                <button wire:click="showBookings({{ $d->id }})" class="text-[12px] text-primary hover:text-primary-deep">Bookings</button>
                                @if($status !== 'past')
                                    <button wire:click="editDay({{ $d->id }})" class="text-[12px] text-ink-secondary hover:text-ink">Edit</button>
                                    <button wire:click="toggleClosed({{ $d->id }})" class="text-[12px] text-ink-secondary hover:text-ink">{{ $d->is_closed ? 'Reopen' : 'Close' }}</button>
                                @endif
                                @if($d->booked() === 0)
                                    <button wire:click="deleteDay({{ $d->id }})" wire:confirm="Delete this order day? It won't be created again." class="text-[12px] text-ink-mute hover:text-ruby">Delete</button>
                                @endif
                            </div>
                        </div>
                        @if($bookingsFor === $d->id)
                            <div class="px-4 pb-3 bg-canvas-soft/60">
                                @forelse($bookings as $b)
                                    <div class="flex flex-wrap items-center justify-between gap-2 py-1.5 border-b border-hairline last:border-0 text-[13px]">
                                        <a href="{{ route('portal.orders.show', $b->order_id) }}" class="font-medium text-primary hover:text-primary-deep">{{ $b->order->reference }}</a>
                                        <span>{{ $b->order->customer_name }} · {{ $b->order->customer_phone }}</span>
                                        <span class="tnum">{{ intdiv((int) $b->quantity, $boxSize) }} {{ \Illuminate\Support\Str::plural('box', intdiv((int) $b->quantity, $boxSize)) }}</span>
                                        <span class="text-ink-mute">{{ $b->order->branch?->name }}</span>
                                        @include('livewire.shared.order-status-badge', ['status' => $b->order->status, 'label' => $b->order->statusLabel()])
                                    </div>
                                @empty
                                    <p class="py-2 text-ink-mute text-[13px]">No bookings yet.</p>
                                @endforelse
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>
        @empty
            <x-ui.card>
                <p class="text-center text-ink-mute py-6">{{ $contracts->isEmpty() ? 'Add a hatchery contract first; its order days appear here.' : 'No order days to show.' }}</p>
            </x-ui.card>
        @endforelse
    @endif
</div>
