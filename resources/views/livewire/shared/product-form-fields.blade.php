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
