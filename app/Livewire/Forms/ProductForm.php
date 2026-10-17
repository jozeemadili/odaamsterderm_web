<?php

namespace App\Livewire\Forms;

use App\Models\AnimalType;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public string $animal_type_id = '';

    public string $product_category_id = '';

    public string $description = '';

    public $image;

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->name = $product->name;
        $this->pack_size = (string) $product->pack_size;
        $this->unit = $product->unit;
        $this->producer_id = (string) $product->producer_id;
        $this->brand = $product->producer_id ? '' : (string) $product->brand;
        $this->animal_type_id = (string) $product->animal_type_id;
        $this->product_category_id = (string) $product->product_category_id;
        $this->description = (string) $product->description;
        $this->image = null;
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
            'product_category_id' => ['required', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'animal_type_id' => 'animal type',
            'product_category_id' => 'category',
            'producer_id' => 'producer',
        ];
    }

    /** @param  array<string, mixed>  $extra  status / proposer / reviewer fields */
    public function save(array $extra = []): Product
    {
        $this->validate();

        $allowed = ProductCategory::forAnimalType((int) $this->animal_type_id)->pluck('id');
        $category = ProductCategory::find($this->product_category_id);

        if (! $allowed->contains($category->parent_id ?? $category->id)) {
            throw ValidationException::withMessages([
                'form.product_category_id' => 'This category is not used for '.AnimalType::find($this->animal_type_id)?->name.'.',
            ]);
        }

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
        ] + $extra;

        if ($this->product) {
            $this->product->update($data);
            $product = $this->product;
        } else {
            $product = Product::create($data + ['slug' => Product::uniqueSlug(trim($this->name).' '.trim($this->pack_size).' '.$data['brand'])]);
        }

        if ($this->image) {
            $product->addMedia($this->image->getRealPath())
                ->usingFileName($this->image->getClientOriginalName())
                ->toMediaCollection('image');
        }

        $this->reset();

        return $product;
    }
}
