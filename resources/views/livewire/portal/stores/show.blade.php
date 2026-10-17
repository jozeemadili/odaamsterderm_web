<div class="space-y-4">
    @if(session('status'))
        <div class="rounded-lg bg-primary-subtle/30 border border-primary/20 text-primary-deep px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <x-ui.card>
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-[20px] font-light text-ink tracking-tight">{{ $store->name }}</h2>
                    @if($store->is_primary)
                        <x-ui.badge tone="primary">primary</x-ui.badge>
                    @endif
                    @if(! $store->isActive())
                        <x-ui.badge tone="danger">suspended by platform{{ $store->suspension_reason ? ': '.$store->suspension_reason : '' }}</x-ui.badge>
                    @elseif(! $store->hasLocation())
                        <x-ui.badge tone="warning">no map location</x-ui.badge>
                    @elseif($store->is_marketplace_enabled)
                        <x-ui.badge tone="success">on marketplace</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">marketplace off</x-ui.badge>
                    @endif
                </div>
                <p class="text-[13px] text-ink-mute mt-1">{{ collect([$store->address, $store->city, $store->region?->name])->filter()->join(', ') ?: 'No address on file' }}</p>
                <p class="text-[13px] text-ink-mute">{{ collect([$store->phone, $store->email])->filter()->join(' · ') }}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @unless($editing)
                    <x-ui.button size="sm" variant="secondary" wire:click="startEditing">Edit details &amp; location</x-ui.button>
                @endunless
                <a href="{{ route('portal.stores.index') }}" class="text-[13px] text-primary hover:text-primary-deep">&larr; Back</a>
            </div>
        </div>

        @unless($editing)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-5">
                <div class="md:col-span-2">
                    @if($store->hasLocation())
                        <div wire:ignore x-data="locationPicker({ lat: {{ $store->latitude }}, lng: {{ $store->longitude }}, editable: false })">
                            <div x-ref="map" class="h-64 rounded-lg border border-hairline z-0"></div>
                        </div>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ $store->latitude }},{{ $store->longitude }}" target="_blank" rel="noopener" class="inline-block mt-2 text-[12px] text-primary hover:text-primary-deep">Open in Google Maps &rarr;</a>
                    @else
                        <div class="h-64 rounded-lg border border-dashed border-hairline flex items-center justify-center text-[13px] text-ink-mute">No map location yet — customers can't find this store.</div>
                    @endif
                </div>
                <div class="space-y-4 text-[13px]">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-ink-mute mb-1">Opening hours</p>
                        @if($store->opening_hours)
                            @foreach($store->openingHoursLines() as $day => $line)
                                <div class="flex justify-between text-ink-secondary"><span>{{ $day }}</span><span class="tnum">{{ $line }}</span></div>
                            @endforeach
                        @else
                            <p class="text-ink-mute">Not set</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-ink-mute mb-1">Pickup instructions</p>
                        <p class="text-ink-secondary">{{ $store->pickup_instructions ?: 'Not set' }}</p>
                    </div>
                </div>
            </div>
        @else
            <form wire:submit="save" class="mt-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-ui.input wire:model="name" label="Store name" id="store_name" :error="$errors->first('name')" />
                    <x-ui.input wire:model="phone" label="Phone" id="store_phone" :error="$errors->first('phone')" />
                    <x-ui.input type="email" wire:model="email" label="Email (optional)" id="store_email" :error="$errors->first('email')" />
                    <x-ui.input wire:model="address" label="Street / physical address" id="store_address" :error="$errors->first('address')" />
                    <x-ui.input wire:model="city" label="City / district" id="store_city" :error="$errors->first('city')" />
                    <x-ui.select wire:model="region_id" label="Region" id="store_region" :error="$errors->first('region_id')">
                        <option value="">— Choose —</option>
                        @foreach($regions as $region)
                            <option value="{{ $region->id }}">{{ $region->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div x-data="locationPicker({ lat: $wire.entangle('latitude'), lng: $wire.entangle('longitude') })">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-[13px] text-ink-mute">Store location — click the map or drag the pin to the store entrance.</p>
                        <x-ui.button size="sm" variant="secondary" x-on:click="useMyLocation()">
                            <span x-show="!locating">Use my current location</span>
                            <span x-show="locating" x-cloak>Locating…</span>
                        </x-ui.button>
                    </div>
                    <div wire:ignore><div x-ref="map" class="h-80 rounded-lg border border-hairline z-0"></div></div>
                    <p class="text-[12px] text-ink-mute mt-2 tnum" x-show="lat">Pinned at <span x-text="lat"></span>, <span x-text="lng"></span></p>
                    <p class="text-[12px] text-ruby mt-1" x-show="error" x-text="error"></p>
                    @error('latitude') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <p class="text-[13px] text-ink-mute mb-2">Opening hours</p>
                        <div class="space-y-1.5">
                            @foreach(\App\Models\Branch::DAYS as $day => $label)
                                <div class="flex items-center gap-3 text-[13px]">
                                    <span class="w-24 text-ink-secondary">{{ $label }}</span>
                                    <label class="inline-flex items-center gap-1.5 text-ink-mute w-20">
                                        <input type="checkbox" wire:model.live="hours.{{ $day }}.closed" class="rounded border-hairline-input text-primary focus:ring-primary"> Closed
                                    </label>
                                    @unless($hours[$day]['closed'] ?? false)
                                        <input type="time" wire:model="hours.{{ $day }}.open" class="rounded-sm border border-hairline-input px-2 py-1 text-[13px]">
                                        <span class="text-ink-mute">to</span>
                                        <input type="time" wire:model="hours.{{ $day }}.close" class="rounded-sm border border-hairline-input px-2 py-1 text-[13px]">
                                    @endunless
                                </div>
                                @error("hours.$day.open") <p class="text-ruby text-[12px] ml-24">{{ $message }}</p> @enderror
                                @error("hours.$day.close") <p class="text-ruby text-[12px] ml-24">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    </div>
                    <div class="space-y-4">
                        <x-ui.textarea wire:model="pickup_instructions" label="Pickup instructions for customers" id="store_pickup" rows="4" placeholder="e.g. Collect at the counter next to the feed store entrance. Bring your order reference." :error="$errors->first('pickup_instructions')"></x-ui.textarea>
                        <label class="flex items-start gap-2 text-[13px] text-ink-secondary">
                            <input type="checkbox" wire:model="is_marketplace_enabled" class="mt-0.5 rounded border-hairline-input text-primary focus:ring-primary">
                            <span>List this store on the marketplace<br><span class="text-[12px] text-ink-mute">Turn off to keep using the store for offline sales only.</span></span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-ui.button type="submit" target="save">Save store</x-ui.button>
                    @if($store->hasLocation())
                        <x-ui.button variant="ghost" wire:click="$set('editing', false)">Cancel</x-ui.button>
                    @endif
                </div>
            </form>
        @endunless
    </x-ui.card>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (at cost)" value="TZS {{ number_format($summary['stockValue'], 0) }}" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Stock value (at selling price)" value="TZS {{ number_format($summary['sellingValue'], 0) }}" tone="primary" />
        </x-ui.card>
        <x-ui.card padding="p-5">
            <x-ui.stat label="Expected profit if sold out" value="TZS {{ number_format($summary['expectedProfit'], 0) }}" :tone="$summary['expectedProfit'] >= 0 ? 'primary' : 'ruby'" />
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0">
        <div class="px-5 py-4 border-b border-hairline">
            <h2 class="text-[15px] text-ink-secondary">Products in this store</h2>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-canvas-soft text-left text-[11px] uppercase tracking-wide text-ink-mute">
                <tr>
                    <th class="px-5 py-3 font-normal">Item</th>
                    <th class="px-5 py-3 font-normal">On hand</th>
                    <th class="px-5 py-3 font-normal">Unit cost</th>
                    <th class="px-5 py-3 font-normal">Selling price</th>
                    <th class="px-5 py-3 font-normal">Online</th>
                    <th class="px-5 py-3 font-normal text-right">Stock value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse($items as $item)
                    <tr class="hover:bg-canvas-soft/60">
                        <td class="px-5 py-3 text-ink font-medium"><a href="{{ route('portal.inventory.show', $item) }}" class="hover:text-primary-deep">{{ $item->name }}</a></td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ rtrim(rtrim($item->quantity_on_hand, '0'), '.') }} {{ $item->unit }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->unit_cost ? number_format($item->unit_cost, 0) : '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->unit_price ? number_format($item->unit_price, 0) : '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink-secondary">{{ $item->is_marketplace_visible ? rtrim(rtrim($item->marketplace_quantity, '0'), '.') : '—' }}</td>
                        <td class="px-5 py-3 tnum text-ink text-right">{{ number_format($item->quantity_on_hand * ($item->unit_cost ?? 0), 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-ink-mute">No products assigned to this store yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
</div>
