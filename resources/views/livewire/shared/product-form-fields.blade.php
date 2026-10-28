{{-- Expects $form (ProductForm), $animalGroups (top-level AnimalType with children) and $formCategories (top-level ProductCategory with children) --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="md:col-span-2">
        <x-ui.input wire:model="form.name" label="Product name" id="product_name" placeholder="e.g. Chicken Starter" :error="$errors->first('form.name')" />
    </div>
    <x-ui.input wire:model="form.pack_size" label="Pack size" id="product_pack" placeholder="e.g. 25KG, 100ml, 1000 doses" :error="$errors->first('form.pack_size')" />
    <x-ui.select wire:model="form.unit" label="Selling unit" id="product_unit" :error="$errors->first('form.unit')">
        @foreach(\App\Models\Product::UNITS as $unit)
            <option value="{{ $unit }}">{{ $unit }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.select wire:model.live="form.animal_type_id" label="Animal type" id="product_animal" :error="$errors->first('form.animal_type_id')">
        <option value="">— Choose —</option>
        @foreach($animalGroups as $group)
            <option value="{{ $group->id }}">{{ $group->name }}</option>
            @foreach($group->children as $child)
                <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
            @endforeach
        @endforeach
    </x-ui.select>
    <x-ui.select wire:model="form.product_category_id" label="Category" id="product_category" :error="$errors->first('form.product_category_id')">
        <option value="">— Choose —</option>
        @foreach($formCategories as $category)
            <option value="{{ $category->id }}">{{ $category->name }}</option>
            @foreach($category->children as $child)
                <option value="{{ $child->id }}">&nbsp;&nbsp;↳ {{ $child->name }}</option>
            @endforeach
        @endforeach
    </x-ui.select>
    <x-ui.select wire:model.live="form.producer_id" label="Producer / factory" id="product_producer" :error="$errors->first('form.producer_id')">
        <option value="">— Not listed / unknown —</option>
        @foreach(\App\Models\Producer::active()->orderBy('name')->get(['id', 'name']) as $producer)
            <option value="{{ $producer->id }}">{{ $producer->name }}</option>
        @endforeach
    </x-ui.select>
    @if($form->producer_id === '')
        <x-ui.input wire:model="form.brand" label="Brand (if the producer is not listed)" id="product_brand" :error="$errors->first('form.brand')" />
    @else
        <p class="self-end pb-2 text-[12px] text-ink-mute">Customers will see “by {{ \App\Models\Producer::find($form->producer_id)?->name }}” with the producer's logo.</p>
    @endif

    @php
        $chosenAnimalIds = array_filter([$form->animal_type_id, ...$form->animal_type_ids]);
        $chosenGroupIds = \App\Models\AnimalType::whereIn('id', $chosenAnimalIds)->get(['id', 'parent_id'])->map(fn ($a) => $a->parent_id ?? $a->id)->all();
        $stageOptions = \App\Models\LifeStage::active()
            ->where(fn ($q) => $q->whereNull('animal_type_id')->orWhereIn('animal_type_id', $chosenGroupIds))
            ->orderBy('sort_order')->get(['id', 'name', 'animal_type_id']);
        $checkboxClass = 'inline-flex items-center gap-1.5 rounded-pill border border-hairline px-2.5 py-1 text-[12px] text-ink-secondary has-[:checked]:border-primary has-[:checked]:text-ink';
    @endphp

    <div class="md:col-span-4">
        <p class="text-[13px] text-ink-mute mb-1.5">Also for (other animals on the label)</p>
        <div class="flex flex-wrap gap-2">
            @foreach($animalGroups as $group)
                @foreach([$group, ...$group->children] as $animal)
                    @continue((string) $animal->id === $form->animal_type_id)
                    <label class="{{ $checkboxClass }}">
                        <input type="checkbox" wire:model.live="form.animal_type_ids" value="{{ $animal->id }}" class="rounded-sm">
                        {{ $animal->parent_id ? $group->name.' › '.$animal->name : $animal->name }}
                    </label>
                @endforeach
            @endforeach
        </div>
        @error('form.animal_type_ids.*') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-4">
        <p class="text-[13px] text-ink-mute mb-1.5">Life stages</p>
        <div class="flex flex-wrap gap-2">
            @foreach($stageOptions as $stage)
                <label class="{{ $checkboxClass }}">
                    <input type="checkbox" wire:model="form.life_stage_ids" value="{{ $stage->id }}" class="rounded-sm">
                    {{ $stage->name }}
                </label>
            @endforeach
        </div>
        @error('form.life_stage_ids.*') <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
    </div>

    <x-ui.select wire:model="form.regulatory_class" label="How it may be sold" id="product_regulatory_class" :error="$errors->first('form.regulatory_class')">
        <option value="">— Not set —</option>
        @foreach(\App\Models\ProductAttribute::options('regulatory_class') as $code => $label)
            <option value="{{ $code }}">{{ $label }}</option>
        @endforeach
    </x-ui.select>
    <div class="md:col-span-3 flex flex-wrap items-end gap-4 pb-2">
        <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary">
            <input type="checkbox" wire:model="form.prescription_required" id="product_prescription" class="rounded-sm"> Prescription needed
        </label>
        <label class="inline-flex items-center gap-2 text-[13px] text-ink-secondary">
            <input type="checkbox" wire:model="form.cold_chain_required" id="product_cold_chain" class="rounded-sm"> Must be kept cold (2–8 °C)
        </label>
    </div>

    <div class="md:col-span-2">
        <x-ui.input wire:model="form.active_ingredients" label="Active ingredients" id="product_ingredients" placeholder="e.g. Albendazole 100 mg/ml" :error="$errors->first('form.active_ingredients')" />
    </div>
    <div class="md:col-span-2 grid grid-cols-3 gap-2">
        <x-ui.input wire:model="form.withdrawal_meat_days" type="number" min="0" label="Meat withdrawal (days)" id="product_withdrawal_meat" :error="$errors->first('form.withdrawal_meat_days')" />
        <x-ui.input wire:model="form.withdrawal_eggs_days" type="number" min="0" label="Eggs (days)" id="product_withdrawal_eggs" :error="$errors->first('form.withdrawal_eggs_days')" />
        <x-ui.input wire:model="form.withdrawal_milk_days" type="number" min="0" label="Milk (days)" id="product_withdrawal_milk" :error="$errors->first('form.withdrawal_milk_days')" />
    </div>
    <div class="md:col-span-4">
        <x-ui.input wire:model="form.search_keywords" label="Search words (separate with |, Kiswahili first)" id="product_keywords" placeholder="e.g. dawa ya minyoo|minyoo|dewormer" :error="$errors->first('form.search_keywords')" />
    </div>

    <div class="md:col-span-3">
        <x-ui.textarea wire:model="form.description" label="Description (shown to customers)" id="product_description" rows="3" :error="$errors->first('form.description')"></x-ui.textarea>
    </div>
    @foreach(['image' => 'Front photo (optional)', 'backImage' => 'Back photo (optional)'] as $field => $label)
        <div>
            <label class="block text-[13px] text-ink-mute mb-1.5" for="product_{{ $field }}">{{ $label }}</label>
            <input type="file" wire:model="form.{{ $field }}" id="product_{{ $field }}" accept="image/*" class="block w-full text-[13px] text-ink-secondary file:mr-3 file:rounded-pill file:border-0 file:bg-canvas-soft file:px-3 file:py-1.5 file:text-[12px]">
            @error('form.'.$field) <p class="text-ruby text-[12px] mt-1">{{ $message }}</p> @enderror
        </div>
    @endforeach
</div>
