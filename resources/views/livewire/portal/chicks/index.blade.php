<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-3xl">You sell day-old chicks for every hatchery below. Add each hatchery's hatch dates with the number of chicks; customers book whole boxes of {{ $boxSize }} chicks until a date is full or its last order day passes, and collect them at your stores. Bookings arrive in <a href="{{ route('portal.orders.index') }}" class="text-primary hover:text-primary-deep">Orders</a>.</p>
        <div class="flex items-center gap-1 rounded-pill border border-hairline bg-canvas p-1 text-[13px]">
            <button wire:click="$set('tab', 'dates')" class="px-3 py-1.5 rounded-pill {{ $tab === 'dates' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Hatch dates</button>
            <button wire:click="$set('tab', 'hatcheries')" class="px-3 py-1.5 rounded-pill {{ $tab === 'hatcheries' ? 'bg-brand-dark text-white' : 'text-ink-secondary hover:text-ink' }}">Hatcheries ({{ $hatcheries->count() }})</button>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-ruby/10 border border-ruby/20 text-ruby px-4 py-2.5 text-[14px]">{{ session('error') }}</div>
    @endif

    @if($tab === 'dates')
        {{-- Filters and actions --}}
        <div class="flex flex-wrap items-end gap-3">
            <x-ui.select wire:model.live="type" label="Chick type" id="flt_type">
                <option value="">All types</option>
                @foreach($chickTypes as $t)<option value="{{ $t->code }}">{{ $t->name }}</option>@endforeach
            </x-ui.select>
            <x-ui.select wire:model.live="hatchery" label="Hatchery" id="flt_hatchery">
                <option value="">All hatcheries</option>
                @foreach($hatcheries as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
            </x-ui.select>
            <label class="flex items-center gap-2 text-[13px] text-ink-secondary pb-2">
                <input type="checkbox" wire:model.live="showPast" class="rounded border-hairline-input text-primary focus:ring-primary"> Show past dates
            </label>
            <div class="ml-auto flex flex-wrap items-end gap-2">
                <form wire:submit="importCsv" class="flex items-end gap-2">
                    <div>
                        <label for="chick_csv" class="block text-[12px] text-ink-mute mb-1" title="Columns: hatchery, chick type, hatch date (YYYY-MM-DD), chicks, price per chick (optional), last order date (optional), notes (optional)">Import CSV <span class="underline decoration-dotted">columns?</span></label>
                        <input type="file" wire:model="csv" id="chick_csv" accept=".csv,text/csv" class="block w-56 text-[12px] text-ink-secondary file:mr-2 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                    </div>
                    <x-ui.button type="submit" variant="secondary" size="sm" target="importCsv">Import</x-ui.button>
                </form>
                <x-ui.button wire:click="newDate">+ Add hatch date</x-ui.button>
            </div>
        </div>
        @error('csv') <p class="text-ruby text-[12px]">{{ $message }}</p> @enderror
        @if($importErrors)
            <div class="rounded-lg bg-canvas-cream border border-lemon/30 px-4 py-2.5 text-[13px] text-ink">
                <p class="font-medium mb-1">Some rows were skipped:</p>
                <ul class="list-disc pl-5 space-y-0.5">@foreach($importErrors as $e)<li>{{ $e }}</li>@endforeach</ul>
                <p class="mt-1 text-ink-mute">Columns: hatchery, chick type (broiler, layers, sasso, tanbro), hatch date (YYYY-MM-DD), chicks, price per chick, last order date, notes.</p>
            </div>
        @endif

        @if($showDateForm)
            <x-ui.card>
                <h2 class="text-[15px] font-medium mb-3">{{ $editingDateId ? 'Edit hatch date' : 'New hatch date' }}</h2>
                <form wire:submit="saveDate" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <x-ui.select wire:model="producer_id" label="Hatchery" id="hs_producer" :error="$errors->first('producer_id')">
                        <option value="">Choose…</option>
                        @foreach($hatcheries->where('is_active', true) as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                    </x-ui.select>
                    <x-ui.select wire:model="chick_type_id" label="Chick type" id="hs_type" :error="$errors->first('chick_type_id')">
                        <option value="">Choose…</option>
                        @foreach($chickTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </x-ui.select>
                    <x-ui.input type="date" wire:model.live="hatch_date" label="Hatch date" id="hs_date" :error="$errors->first('hatch_date')" />
                    <x-ui.input type="date" wire:model="order_cutoff_date" label="Last day to order" id="hs_cutoff" :error="$errors->first('order_cutoff_date')" />
                    <x-ui.input type="number" min="1" wire:model="capacity" label="Chicks this hatch gives" id="hs_capacity" :error="$errors->first('capacity')" />
                    <x-ui.input type="number" min="0" step="any" wire:model="price_per_chick" label="Price per chick (TZS)" id="hs_price" :error="$errors->first('price_per_chick')" />
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="notes" label="Note for customers (optional)" id="hs_notes" placeholder="e.g. Vaccinated against Marek's and Newcastle" :error="$errors->first('notes')" />
                    </div>
                    <div class="md:col-span-4 flex gap-2">
                        <x-ui.button type="submit" target="saveDate">{{ $editingDateId ? 'Save changes' : 'Add hatch date' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showDateForm', false)">Cancel</x-ui.button>
                        <p class="ml-auto self-center text-[12px] text-ink-mute">Customers book boxes of {{ $boxSize }} chicks. The last day to order defaults to {{ \App\Models\HatchSchedule::cutoffDays() }} days before hatching.</p>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                        <tr>
                            <th class="px-4 py-3 font-normal">Hatch date</th>
                            <th class="px-4 py-3 font-normal">Hatchery</th>
                            <th class="px-4 py-3 font-normal">Type</th>
                            <th class="px-4 py-3 font-normal text-right">Chicks</th>
                            <th class="px-4 py-3 font-normal text-right">Booked</th>
                            <th class="px-4 py-3 font-normal text-right">Boxes left</th>
                            <th class="px-4 py-3 font-normal">Price / chick</th>
                            <th class="px-4 py-3 font-normal">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($dates as $d)
                            @php($status = $d->status())
                            <tr class="hover:bg-canvas-soft/60" wire:key="hs-{{ $d->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-ink">{{ $d->hatch_date->format('D j M Y') }}</p>
                                    <p class="text-[11.5px] text-ink-mute">Order by {{ $d->order_cutoff_date->format('j M') }}</p>
                                </td>
                                <td class="px-4 py-3 text-ink">{{ $d->producer->name }}</td>
                                <td class="px-4 py-3">{{ $d->chickType->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ number_format($d->capacity) }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ number_format($d->booked()) }}</td>
                                <td class="px-4 py-3 text-right tnum font-medium">{{ $d->boxesAvailable() }}</td>
                                <td class="px-4 py-3 tnum">{{ $d->price_per_chick !== null ? 'TZS '.number_format($d->price_per_chick) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :tone="match($status) { 'open' => 'success', 'full' => 'warning', default => 'neutral' }">{{ $d->statusLabel() }}</x-ui.badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                    <button wire:click="showBookings({{ $d->id }})" class="text-[12px] text-primary hover:text-primary-deep">Bookings</button>
                                    @if($status !== 'past')
                                        <button wire:click="editDate({{ $d->id }})" class="text-[12px] text-ink-secondary hover:text-ink">Edit</button>
                                        <button wire:click="toggleClosed({{ $d->id }})" class="text-[12px] text-ink-secondary hover:text-ink">{{ $d->is_closed ? 'Reopen' : 'Close' }}</button>
                                    @endif
                                    @if($d->booked() === 0)
                                        <button wire:click="deleteDate({{ $d->id }})" wire:confirm="Delete this hatch date?" class="text-[12px] text-ink-mute hover:text-ruby">Delete</button>
                                    @endif
                                </td>
                            </tr>
                            @if($bookingsFor === $d->id)
                                <tr class="bg-canvas-soft/60">
                                    <td colspan="9" class="px-4 py-3">
                                        @forelse($bookings as $b)
                                            <div class="flex flex-wrap items-center justify-between gap-2 py-1.5 border-b border-hairline last:border-0">
                                                <a href="{{ route('portal.orders.show', $b->order_id) }}" class="font-medium text-primary hover:text-primary-deep">{{ $b->order->reference }}</a>
                                                <span>{{ $b->order->customer_name }} · {{ $b->order->customer_phone }}</span>
                                                <span class="tnum">{{ intdiv((int) $b->quantity, $boxSize) }} {{ \Illuminate\Support\Str::plural('box', intdiv((int) $b->quantity, $boxSize)) }} ({{ number_format($b->quantity) }} chicks)</span>
                                                <span class="text-ink-mute">{{ $b->order->branch?->name }}</span>
                                                @include('livewire.shared.order-status-badge', ['status' => $b->order->status, 'label' => $b->order->statusLabel()])
                                            </div>
                                        @empty
                                            <p class="text-ink-mute">No bookings yet.</p>
                                        @endforelse
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="px-4 py-10 text-center text-ink-mute">No hatch dates yet. Add one, or import a CSV.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @else
        <div class="flex justify-end">
            <x-ui.button wire:click="newHatchery">+ Add hatchery</x-ui.button>
        </div>

        @if($showHatcheryForm)
            <x-ui.card>
                <h2 class="text-[15px] font-medium mb-3">{{ $editingHatcheryId ? 'Edit hatchery' : 'New hatchery' }}</h2>
                <form wire:submit="saveHatchery" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="name" label="Company name" id="hc_name" :error="$errors->first('name')" />
                    </div>
                    <x-ui.select wire:model="deals_in" label="Produces" id="hc_deals" :error="$errors->first('deals_in')">
                        <option value="chicks">Chicks only</option>
                        <option value="chicks_feed">Chicks and feed</option>
                    </x-ui.select>
                    <x-ui.input wire:model="phone" label="Phone" id="hc_phone" :error="$errors->first('phone')" />
                    <div class="md:col-span-2">
                        <x-ui.input wire:model="location" label="Location" id="hc_location" placeholder="e.g. Moshi, Kilimanjaro" :error="$errors->first('location')" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[13px] text-ink-mute mb-1.5" for="hc_logo">Logo (optional)</label>
                        <input type="file" wire:model="logo" id="hc_logo" accept="image/*" class="block w-full text-[13px] text-ink-secondary file:mr-3 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
                        @error('logo') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-4">
                        <p class="text-[13px] text-ink-mute mb-1.5">Chick types it produces</p>
                        <div class="flex flex-wrap gap-4">
                            @foreach($chickTypes as $t)
                                <label class="flex items-center gap-2 text-[14px]">
                                    <input type="checkbox" wire:model="types" value="{{ $t->id }}" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $t->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('types') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-4">
                        <x-ui.textarea wire:model="description" label="About (shown to customers, optional)" id="hc_about" rows="2" :error="$errors->first('description')"></x-ui.textarea>
                    </div>
                    <div class="md:col-span-4 flex gap-2">
                        <x-ui.button type="submit" target="saveHatchery">{{ $editingHatcheryId ? 'Save changes' : 'Add hatchery' }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('showHatcheryForm', false)">Cancel</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        <x-ui.card padding="p-0">
            <table class="w-full text-[13px]">
                <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                    <tr>
                        <th class="px-4 py-3 font-normal">Hatchery</th>
                        <th class="px-4 py-3 font-normal">Chick types</th>
                        <th class="px-4 py-3 font-normal">Produces</th>
                        <th class="px-4 py-3 font-normal">Contact</th>
                        <th class="px-4 py-3 font-normal text-right">Upcoming dates</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach($hatcheries as $h)
                        <tr class="hover:bg-canvas-soft/60 {{ $h->is_active ? '' : 'text-ink-mute' }}" wire:key="hc-{{ $h->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($h->logoUrl())
                                        <img src="{{ $h->logoUrl() }}" alt="" class="h-9 w-9 rounded-full object-contain border border-hairline bg-white">
                                    @else
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-subtle text-[11px] font-bold text-primary">{{ collect(preg_split('/\s+/', $h->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
                                    @endif
                                    <div>
                                        <p class="font-medium {{ $h->is_active ? 'text-ink' : '' }}">{{ $h->name }}</p>
                                        @if($h->location)<p class="text-[11.5px] text-ink-mute">{{ $h->location }}</p>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $h->chickTypes->pluck('name')->join(', ') ?: '—' }}</td>
                            <td class="px-4 py-3">{{ \App\Models\Producer::DEALS[$h->deals_in] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $h->phone ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tnum">{{ $h->upcoming_dates }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                <button wire:click="$set('hatchery', '{{ $h->id }}'); $set('tab', 'dates')" class="text-[12px] text-primary hover:text-primary-deep">Hatch dates</button>
                                <button wire:click="editHatchery({{ $h->id }})" class="text-[12px] text-ink-secondary hover:text-ink">Edit</button>
                                <button wire:click="toggleHatchery({{ $h->id }})" class="text-[12px] text-ink-mute hover:text-ink">{{ $h->is_active ? 'Hide' : 'Show' }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif
</div>
