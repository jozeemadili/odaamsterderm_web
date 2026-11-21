<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-mute max-w-3xl">Products agents couldn't find in the catalogue. Link it to a product that's already there, or build it from the label photos (AI drafts it, you check it). The agent's stock links automatically either way.</p>
        <div class="flex gap-1.5">
            @foreach(\App\Models\ProductRequest::LABELS as $code => $label)
                <button type="button" wire:click="$set('status', '{{ $code }}')" class="rounded-pill border px-3 py-1 text-[12.5px] {{ $status === $code ? 'border-primary bg-primary text-white' : 'border-hairline text-ink-secondary' }}">{{ $label }}@if($code === 'pending' && $pendingCount) ({{ $pendingCount }})@endif</button>
            @endforeach
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-success-subtle border border-success/20 text-success px-4 py-2.5 text-[14px]">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-4 items-start">
        <x-ui.card padding="p-0">
            <div class="divide-y divide-hairline max-h-[75vh] overflow-y-auto">
                @forelse($requests as $r)
                    <button type="button" wire:click="select({{ $r->id }})" wire:key="rq-{{ $r->id }}" class="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-canvas-soft {{ $selected === $r->id ? 'bg-canvas-soft' : '' }}">
                        @if($r->photoUrl('label_front'))<img src="{{ $r->photoUrl('label_front') }}" alt="" class="h-10 w-10 rounded object-cover border border-hairline">@endif
                        <span class="min-w-0 text-[13px]">
                            <span class="block font-medium text-ink truncate">{{ $r->name }}</span>
                            <span class="block text-ink-mute truncate">{{ $r->merchant?->business_name }} · {{ $r->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-[13px] text-ink-mute">Nothing here.</p>
                @endforelse
            </div>
        </x-ui.card>

        @if($current)
            <div class="space-y-4" wire:key="current-{{ $current->id }}">
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-[16px] font-medium text-ink">{{ $current->name }}</h2>
                            <p class="text-[13px] text-ink-mute">{{ $current->producerLabel() }} · {{ $current->pack_size }} · from {{ $current->merchant?->business_name }}@if($current->requester) ({{ $current->requester->name }})@endif @if($current->inventoryItem?->branch) · in stock at {{ $current->inventoryItem->branch->name }}@endif</p>
                            @if($current->notes)<p class="mt-1 text-[13px] text-ink-secondary">“{{ $current->notes }}”</p>@endif
                        </div>
                        <x-ui.badge :tone="match($current->status) { 'pending' => 'warning', 'rejected' => 'danger', default => 'success' }">{{ $current->statusLabel() }}</x-ui.badge>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        @foreach(['label_front' => 'Front', 'label_back' => 'Back'] as $side => $label)
                            <div>
                                <p class="text-[12px] text-ink-mute mb-1">{{ $label }}</p>
                                @if($current->photoUrl($side))
                                    <a href="{{ $current->photoUrl($side) }}" target="_blank" rel="noopener"><img src="{{ $current->photoUrl($side) }}" alt="{{ $label }} of the label" class="w-full max-h-80 rounded border border-hairline object-contain bg-white"></a>
                                @else
                                    <div class="h-32 rounded border border-dashed border-hairline"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($current->status !== 'pending' && $current->review_notes)
                        <p class="mt-3 text-[13px] text-ink-secondary">Review note: {{ $current->review_notes }}</p>
                    @endif
                </x-ui.card>

                @if($current->status === 'pending')
                    @if($matches->isNotEmpty())
                        <x-ui.card>
                            <h3 class="text-[14px] font-medium mb-2">Already in the catalogue?</h3>
                            <div class="flex flex-wrap items-end gap-3">
                                <x-ui.select wire:model="linkVariant" label="Matching product and pack size" id="link_variant" :error="$errors->first('linkVariant')">
                                    <option value="">Choose…</option>
                                    @foreach($matches as $m)
                                        <optgroup label="{{ $m->name }} — {{ $m->producer?->name ?? $m->brand }}">
                                            @foreach($m->variants as $v)<option value="{{ $v->id }}">{{ $m->name }} · {{ $v->name }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.button wire:click="link" target="link">Link to this product</x-ui.button>
                            </div>
                        </x-ui.card>
                    @endif

                    <x-ui.card>
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <h3 class="text-[14px] font-medium">New catalogue product</h3>
                            @if($aiReady)
                                <x-ui.button variant="secondary" size="sm" wire:click="draft" target="draft">{{ $current->ai_draft ? 'Draft again with AI' : 'Draft with AI from the label' }}</x-ui.button>
                            @else
                                <span class="text-[12px] text-ink-mute">AI drafting is off: add the key in Settings → AI Assistant.</span>
                            @endif
                        </div>
                        @if($draftMessage)
                            <div class="mb-3 rounded-lg bg-canvas-cream border border-lemon/40 px-3 py-2 text-[13px] text-ink">{{ $draftMessage }}</div>
                        @endif

                        <form wire:submit="approve" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-ui.input wire:model="form.name" label="Product name (without pack size)" id="f_name" :error="$errors->first('form.name')" />
                                <div class="grid grid-cols-2 gap-3 items-end">
                                    <x-ui.select wire:model.live="form.producer_id" label="Producer" id="f_producer">
                                        <option value="">New producer →</option>
                                        @foreach($producers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                    </x-ui.select>
                                    @if(($form['producer_id'] ?? '') === '')
                                        <x-ui.input wire:model="form.producer_name" label="Producer name" id="f_producer_name" />
                                    @endif
                                </div>
                                <x-ui.select wire:model="form.category_code" label="Category" id="f_category" :error="$errors->first('form.category_code')">
                                    <option value="">Choose…</option>
                                    @foreach($categories as $c)<option value="{{ $c->code }}">{{ $c->parent ? $c->parent->name.' › ' : '' }}{{ $c->name }}</option>@endforeach
                                </x-ui.select>
                                <x-ui.select wire:model="form.form" label="Form" id="f_form">
                                    <option value="">—</option>
                                    @foreach($options('form') as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                </x-ui.select>
                            </div>

                            <div>
                                <p class="text-[13px] text-ink-mute mb-1.5">Animals</p>
                                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[13px]">
                                    @foreach($animals as $a)
                                        <label class="flex items-center gap-1.5"><input type="checkbox" value="{{ $a->code }}" wire:model="form.animal_codes" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $a->name }}</label>
                                    @endforeach
                                </div>
                                @error('form.animal_codes') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <p class="text-[13px] text-ink-mute mb-1.5">Growth stages</p>
                                <div class="space-y-1.5 text-[13px]">
                                    @foreach($stages->groupBy(fn ($s) => $s->animalType?->name ?? 'Any animal') as $animal => $group)
                                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                                            <span class="w-36 shrink-0 text-ink-mute">{{ $animal }}</span>
                                            @foreach($group as $s)
                                                <label class="flex items-center gap-1.5"><input type="checkbox" value="{{ $s->code }}" wire:model="form.life_stage_codes" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $s->name }}</label>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <p class="text-[13px] text-ink-mute mb-1.5">Pack sizes</p>
                                <div class="space-y-2">
                                    @foreach($form['pack_sizes'] ?? [] as $i => $pack)
                                        <div class="flex flex-wrap items-center gap-2 text-[13px]" wire:key="pack-{{ $i }}">
                                            <input type="text" wire:model="form.pack_sizes.{{ $i }}.name" placeholder="Name, e.g. 100 g sachet" aria-label="Pack size name" class="w-48 rounded-sm border border-hairline-input px-2 py-1">
                                            <input type="number" step="any" min="0" wire:model="form.pack_sizes.{{ $i }}.value" placeholder="Amount" aria-label="Amount" class="w-24 rounded-sm border border-hairline-input px-2 py-1 tnum">
                                            <select wire:model="form.pack_sizes.{{ $i }}.unit" aria-label="Unit" class="rounded-sm border border-hairline-input px-2 py-1">
                                                <option value="">unit</option>
                                                @foreach($units as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach
                                            </select>
                                            <input type="text" wire:model="form.pack_sizes.{{ $i }}.selling_unit" placeholder="Sold per (bag, bottle…)" aria-label="Sold per" class="w-36 rounded-sm border border-hairline-input px-2 py-1">
                                            @if(count($form['pack_sizes']) > 1)
                                                <button type="button" wire:click="removePack({{ $i }})" class="text-ink-mute hover:text-ruby" aria-label="Remove pack size">✕</button>
                                            @endif
                                            @error("form.pack_sizes.$i.name") <span class="text-ruby text-[12px]">{{ $message }}</span> @enderror
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" wire:click="addPack" class="mt-2 text-[13px] text-primary hover:text-primary-deep">+ Another pack size</button>
                                <p class="mt-1 text-[12px] text-ink-mute">The agent's stock links to the first pack size.</p>
                            </div>

                            <details class="rounded-[12px] border border-hairline p-3" @if(($form['active_ingredients'] ?? '') !== '' || ($form['dosage'] ?? '') !== '' || $errors->has('product')) open @endif>
                                <summary class="cursor-pointer text-[13px] font-medium">Label details (ingredients, dosage, withdrawal…)</summary>
                                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <x-ui.textarea wire:model="form.description" label="Description" id="f_desc" rows="2" />
                                    <x-ui.textarea wire:model="form.active_ingredients" label="Active ingredients" id="f_ingr" rows="2" />
                                    <x-ui.textarea wire:model="form.dosage" label="Dosage / use rate" id="f_dose" rows="2" />
                                    <x-ui.input wire:model="form.storage" label="Storage" id="f_storage" />
                                    <x-ui.input wire:model="form.registration_no" label="Registration no. (TVLA)" id="f_reg" />
                                    <x-ui.input wire:model="form.keywords" label="Search words (Kiswahili, local names)" id="f_kw" placeholder="e.g. dawa ya minyoo" />
                                    <div class="grid grid-cols-3 gap-2">
                                        <x-ui.input type="number" min="0" wire:model="form.withdrawal_meat_days" label="Meat (days)" id="f_wm" />
                                        <x-ui.input type="number" min="0" wire:model="form.withdrawal_eggs_days" label="Eggs (days)" id="f_we" />
                                        <x-ui.input type="number" min="0" wire:model="form.withdrawal_milk_days" label="Milk (days)" id="f_wk" />
                                    </div>
                                    <div class="flex flex-wrap gap-4 self-end">
                                        <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" wire:model="form.prescription_required" class="rounded border-hairline-input text-primary focus:ring-primary"> Prescription required</label>
                                        <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" wire:model="form.cold_chain_required" class="rounded border-hairline-input text-primary focus:ring-primary"> Keep cold (2–8 °C)</label>
                                    </div>
                                    <x-ui.input type="number" step="any" min="0" wire:model="form.crude_protein_pct" label="Crude protein % (feeds)" id="f_protein" />
                                    <x-ui.input wire:model="form.breed_strain" label="Breed / strain (live animals)" id="f_breed" />
                                    <x-ui.input wire:model="form.capacity" label="Capacity (equipment)" id="f_capacity" placeholder="e.g. 10 L, 500 eggs" />
                                    <x-ui.select wire:model="form.power_source" label="Power source (equipment)" id="f_power">
                                        <option value="">—</option>
                                        @foreach($options('power_source') as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                    </x-ui.select>
                                </div>
                                @foreach(['use_cases' => 'Used for', 'disease_target' => 'Treats / prevents', 'administration_route' => 'Given by'] as $key => $label)
                                    @php($opts = $options($key))
                                    @if($opts)
                                        <div class="mt-4">
                                            <p class="text-[13px] text-ink-mute mb-1.5">{{ $label }}</p>
                                            <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[13px]">
                                                @foreach($opts as $code => $optLabel)
                                                    <label class="flex items-center gap-1.5"><input type="checkbox" value="{{ $code }}" wire:model="form.{{ $key }}" class="rounded border-hairline-input text-primary focus:ring-primary"> {{ $optLabel }}</label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </details>

                            @error('product')
                                <div class="rounded-lg bg-ruby/10 text-ruby px-3 py-2 text-[13px]">{{ $message }} Open “Label details” to fill them.</div>
                            @enderror
                            <x-ui.button type="submit" target="approve">Add to catalogue &amp; link the agent's stock</x-ui.button>
                        </form>
                    </x-ui.card>

                    <x-ui.card>
                        <form wire:submit="reject" class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[260px]">
                                <x-ui.input wire:model="rejectReason" label="Decline: reason the agent will see" id="reject_reason" placeholder="e.g. Photos are blurry; please retake the back label" :error="$errors->first('rejectReason')" />
                            </div>
                            <x-ui.button type="submit" variant="danger" target="reject">Decline</x-ui.button>
                        </form>
                    </x-ui.card>
                @elseif($current->product)
                    <x-ui.card>
                        <p class="text-[13px]">In the catalogue as <a href="{{ route('admin.products.index', ['search' => $current->product->name]) }}" class="text-primary">{{ $current->product->name }}</a>.</p>
                    </x-ui.card>
                @endif
            </div>
        @else
            <x-ui.card><p class="py-8 text-center text-[13px] text-ink-mute">Choose a request on the left.</p></x-ui.card>
        @endif
    </div>
</div>
