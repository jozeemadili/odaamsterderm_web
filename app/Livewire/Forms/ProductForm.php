<?php

namespace App\Livewire\Forms;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Catalog product fields, shared by the admin catalog screen and the
 * seller "propose a product" flow.
 */
class ProductForm extends Form
{
    public ?Product $product = null;

    public string $name = '';

    public string $pack_size = '';

    public string $unit = 'bag';

    public string $producer_id = '';

    /** Free-text brand, only when the producer is not in the list yet. */
    public string $brand = '';

    /** The main animal, shown on product cards. */
    public string $animal_type_id = '';

    /** Other animals the label covers (Albendazole: Cattle + Goats & Sheep). */
    public array $animal_type_ids = [];

    public array $life_stage_ids = [];

    public string $regulatory_class = '';

    public bool $prescription_required = false;

    public bool $cold_chain_required = false;

    public string $active_ingredients = '';

    public string $withdrawal_meat_days = '';

    public string $withdrawal_eggs_days = '';

    public string $withdrawal_milk_days = '';

    /** Extra search words, e.g. Kiswahili names: "chanjo|kideri". */
    public string $search_keywords = '';

    public string $product_category_id = '';

    public string $description = '';

    public $image;

    public $backImage;

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->name = $product->name;
        $this->pack_size = (string) $product->pack_size;
        $this->unit = $product->unit;
        $this->producer_id = (string) $product->producer_id;
        $this->brand = $product->producer_id ? '' : (string) $product->brand;
        $this->animal_type_id = (string) $product->animal_type_id;
        $this->animal_type_ids = $product->animalTypes()->pluck('animal_types.id')
            ->reject(fn ($id) => $id === $product->animal_type_id)->map(fn ($id) => (string) $id)->values()->all();
        $this->life_stage_ids = $product->lifeStages()->pluck('life_stages.id')->map(fn ($id) => (string) $id)->all();
        $this->regulatory_class = (string) $product->regulatory_class;
        $this->prescription_required = $product->prescription_required;
        $this->cold_chain_required = $product->cold_chain_required;
        $this->active_ingredients = (string) $product->active_ingredients;
        $this->withdrawal_meat_days = (string) $product->withdrawal_meat_days;
        $this->withdrawal_eggs_days = (string) $product->withdrawal_eggs_days;
        $this->withdrawal_milk_days = (string) $product->withdrawal_milk_days;
        $this->search_keywords = (string) $product->search_keywords;
        $this->product_category_id = (string) $product->product_category_id;
        $this->description = (string) $product->description;
        $this->image = null;
        $this->backImage = null;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pack_size' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'producer_id' => ['nullable', Rule::exists('producers', 'id')->where('is_active', true)],
            'brand' => ['nullable', 'string', 'max:255'],
            'animal_type_id' => ['required', Rule::exists('animal_types', 'id')->where('is_active', true)],
            'animal_type_ids' => ['array'],
            'animal_type_ids.*' => [Rule::exists('animal_types', 'id')->where('is_active', true)],
            'life_stage_ids' => ['array'],
            'life_stage_ids.*' => [Rule::exists('life_stages', 'id')->where('is_active', true)],
            'regulatory_class' => ['nullable', Rule::in(array_keys(ProductAttribute::options('regulatory_class')))],
            'prescription_required' => ['boolean'],
            'cold_chain_required' => ['boolean'],
            'active_ingredients' => ['nullable', 'string', 'max:500'],
            'withdrawal_meat_days' => ['nullable', 'integer', 'between:0,365'],
            'withdrawal_eggs_days' => ['nullable', 'integer', 'between:0,365'],
            'withdrawal_milk_days' => ['nullable', 'integer', 'between:0,365'],
            'search_keywords' => ['nullable', 'string', 'max:1000'],
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'backImage' => ['nullable', 'image', 'max:4096'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'animal_type_id' => 'animal type',
            'product_category_id' => 'category',
            'producer_id' => 'producer',
            'animal_type_ids.*' => 'animal type',
            'life_stage_ids.*' => 'life stage',
            'withdrawal_meat_days' => 'meat withdrawal',
            'withdrawal_eggs_days' => 'egg withdrawal',
            'withdrawal_milk_days' => 'milk withdrawal',
        ];
    }

    /** @param  array<string, mixed>  $extra  status / proposer / reviewer fields */
    public function save(array $extra = []): Product
    {
        $this->validate();

        $data = [
            'name' => trim($this->name),
            'pack_size' => trim($this->pack_size) ?: null,
            'unit' => $this->unit,
            'producer_id' => $this->producer_id !== '' ? (int) $this->producer_id : null,
            'brand' => $this->producer_id !== ''
                ? Producer::whereKey($this->producer_id)->value('name')
                : (trim($this->brand) ?: null),
            'animal_type_id' => (int) $this->animal_type_id,
            'product_category_id' => (int) $this->product_category_id,
            'description' => trim($this->description) ?: null,
            'regulatory_class' => $this->regulatory_class ?: null,
            'prescription_required' => $this->prescription_required,
            'cold_chain_required' => $this->cold_chain_required,
            'active_ingredients' => trim($this->active_ingredients) ?: null,
            'withdrawal_meat_days' => $this->withdrawal_meat_days !== '' ? (int) $this->withdrawal_meat_days : null,
            'withdrawal_eggs_days' => $this->withdrawal_eggs_days !== '' ? (int) $this->withdrawal_eggs_days : null,
            'withdrawal_milk_days' => $this->withdrawal_milk_days !== '' ? (int) $this->withdrawal_milk_days : null,
            'search_keywords' => mb_strtolower(trim($this->search_keywords)) ?: null,
        ] + $extra;

        if ($this->product) {
            $this->product->update($data);
            $product = $this->product;
        } else {
            $product = Product::create($data + ['slug' => Product::uniqueSlug(trim($this->name).' '.trim($this->pack_size).' '.$data['brand'])]);
        }

        $product->syncDefaultVariant();
        $product->animalTypes()->sync(array_unique([$data['animal_type_id'], ...array_map('intval', $this->animal_type_ids)]));
        $product->lifeStages()->sync(array_map('intval', $this->life_stage_ids));

        if ($this->image) {
            $product->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        if ($this->backImage) {
            $product->addMedia($this->backImage->getRealPath())
                ->usingFileName($this->backImage->getClientOriginalName())
                ->toMediaCollection(Product::BACK_PHOTO);
        }

        $this->reset();

        return $product;
    }
}
