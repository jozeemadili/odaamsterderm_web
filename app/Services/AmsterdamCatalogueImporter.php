<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

/**
 * Loads the product catalogues in database/data on top of the taxonomy
 * workbook: Amsterdam Group's own catalogue (amsterdam-catalogue.json, typed
 * in from their PDF catalogues) and suppliers' ranges such as Silverlands
 * (silverlands-catalogue.json, from their website). They add full
 * descriptions, use rates, extra pack sizes, pack photos and the products
 * the workbook lacks.
 *
 * Rows are matched on their product code (P034 is the workbook's Vitamix
 * Chick, P116+ are new) and variants on their SKU, so re-running updates in
 * place and never touches stock. A catalogue marked show_under_owner lists
 * its products under the catalogue owner (hub.catalogue_producer); others
 * keep their maker. Catalogue pack photos are the products' own photos, so
 * customers see them on every card.
 */
class AmsterdamCatalogueImporter
{
    /** Catalogue files, loaded in this order. */
    public const DATA_PATHS = [
        'database/data/amsterdam-catalogue.json',
        'database/data/silverlands-catalogue.json',
        'database/data/ricagrovet-catalogue.json',
    ];

    /** Pack photos of every catalogue file. */
    public const PHOTO_DIR = 'database/data/amsterdam-catalogue';

    /**
     * @param  string|null  $path  one catalogue file; all of DATA_PATHS when null
     * @return array{producers: int, products: int, product_variants: int, photos_added: int}
     */
    public function import(?string $path = null): array
    {
        if (! ProductCategory::whereNotNull('code')->exists()) {
            throw new RuntimeException('Import the taxonomy workbook first (php artisan catalog:import-taxonomy).');
        }

        $totals = ['producers' => 0, 'products' => 0, 'product_variants' => 0, 'photos_added' => 0];

        foreach ($path ? [$path] : array_map(base_path(...), self::DATA_PATHS) as $file) {
            foreach ($this->importFile($file) as $what => $n) {
                $totals[$what] += $n;
            }
        }

        return $totals;
    }

    /** @return array{producers: int, products: int, product_variants: int, photos_added: int} */
    private function importFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Catalogue file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $underOwner = $data['show_under_owner'] ?? true;

        return DB::transaction(function () use ($data, $path, $underOwner) {
            foreach ($data['producers'] as $row) {
                $this->importProducer($row, dirname($path));
            }

            $variants = $photos = 0;
            $photoDir = dirname($path).'/'.basename(self::PHOTO_DIR).'/';

            foreach ($data['products'] as $row) {
                $product = $this->importProduct($row, $underOwner);
                $variants += count($row['variants']);
                $photos += (int) (isset($row['photo']) && $this->setPhoto($product, 'image', $photoDir.$row['photo']));
                $photos += (int) (isset($row['photo_back']) && $this->setPhoto($product, Product::BACK_PHOTO, $photoDir.$row['photo_back']));
            }

            return [
                'producers' => count($data['producers']),
                'products' => count($data['products']),
                'product_variants' => $variants,
                'photos_added' => $photos,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $dataDir  the catalogue file's folder; a producer's
     *                           `logo` path is relative to it
     */
    private function importProducer(array $row, string $dataDir): void
    {
        $producer = Producer::where('code', $row['code'])->first()
            ?? Producer::whereNull('code')->where('name', $row['name'])->first()
            ?? new Producer(['is_active' => true]);

        $producer->slug ??= $this->uniqueProducerSlug($row['name']);
        $producer->fill([
            'code' => $row['code'],
            'name' => $row['name'],
            'role' => $row['role'],
            'country' => $row['country'] ?? null,
            'is_demo' => false,
        ])->save();

        if (isset($row['logo'])) {
            $this->setPhoto($producer, 'logo', $dataDir.'/'.$row['logo']);
        }
    }

    /** @param  array<string, mixed>  $row */
    private function importProduct(array $row, bool $underOwner): Product
    {
        $code = $row['code'];
        // The maker on the pack, and who the product is shown under (the
        // catalogue owner, when one is configured).
        $maker = Producer::where('code', $row['producer'])->first()
            ?? throw new RuntimeException("Product {$code} uses unknown producer '{$row['producer']}'.");
        $producer = $underOwner && config('hub.catalogue_producer')
            ? Producer::where('code', config('hub.catalogue_producer'))->first() ?? $maker
            : $maker;
        $category = ProductCategory::where('code', $row['category'])->value('id')
            ?? throw new RuntimeException("Product {$code} uses unknown category '{$row['category']}'.");
        $animals = array_map(fn ($a) => AnimalType::where('code', $a)->value('id')
            ?? throw new RuntimeException("Product {$code} uses unknown animal type '{$a}'."), $row['animals']);
        $stages = array_map(fn ($s) => LifeStage::where('code', $s)->value('id')
            ?? throw new RuntimeException("Product {$code} uses unknown life stage '{$s}'."), $row['life_stages']);

        $product = Product::where('code', $code)->first()
            ?? new Product(['status' => Product::STATUS_APPROVED, 'reviewed_at' => now()]);

        $product->slug ??= Product::uniqueSlug($row['name'].' '.$maker->name);
        $product->fill(array_filter([
            'code' => $code,
            'name' => $row['name'],
            'description' => isset($row['tagline']) ? $row['tagline']."\n\n".$row['description'] : $row['description'],
            'product_category_id' => $category,
            'producer_id' => $producer->id,
            'brand' => $producer->name,
            'animal_type_id' => $animals[0],
            'form' => $row['form'],
            'inclusion_or_dosage' => $row['dosage'],
            'crude_protein_pct' => $row['crude_protein'] ?? null,
            'country_of_origin' => $row['country'] ?? $maker->country,
            'search_keywords' => isset($row['keywords']) ? Str::lower($row['keywords']) : null,
            'prescription_required' => $row['prescription_required'] ?? null,
            'use_cases' => $row['use_cases'] ?? null,
            'disease_target' => $row['disease_target'] ?? null,
            'administration_route' => $row['administration_route'] ?? null,
            'active_ingredients' => $row['active_ingredients'] ?? null,
            'unit' => $row['variants'][0]['selling_unit'],
            'is_demo' => false,
        ], fn ($value) => $value !== null));
        // A single pack of known size names the product ("… 500 ml bottle");
        // a pack whose size is still unknown leaves the name alone.
        $product->pack_size = count($row['variants']) === 1 && ($row['variants'][0]['value'] ?? null) !== null
            ? $row['variants'][0]['name']
            : null;
        $product->save();

        $product->animalTypes()->sync($animals);
        $product->lifeStages()->sync($stages);

        foreach ($row['variants'] as $sort => $variant) {
            $this->importVariant($product, $variant, $sort);
        }

        return $product;
    }

    /**
     * Matched on SKU, or a SKU-less variant with the same pack (the pack a
     * product was typed in with).
     *
     * @param  array<string, mixed>  $row
     */
    private function importVariant(Product $product, array $row, int $sort): void
    {
        $variant = ProductVariant::where('sku', $row['sku'])->first()
            ?? $product->variants()->whereNull('sku')->get()
                ->first(fn (ProductVariant $v) => ProductVariant::packKey($v->name) === ProductVariant::packKey($row['name']))
            ?? new ProductVariant(['product_id' => $product->id]);

        $variant->fill([
            'sku' => $row['sku'],
            'name' => $row['name'],
            'pack_size_value' => $row['value'],
            'pack_size_unit' => $row['unit'],
            'selling_unit' => $row['selling_unit'],
            'sort_order' => $sort,
        ])->save();
    }

    /**
     * The catalogue's pack photo is the product's photo, in front of any
     * seller's. Replaced only when the catalogue's file changes, so a photo
     * an admin uploaded for a product the catalogue has no photo for stays.
     */
    private function setPhoto(HasMedia $model, string $collection, string $file): bool
    {
        $current = $model->getFirstMedia($collection);
        $name = basename($file);

        if (! is_file($file) || $current?->getCustomProperty('catalogue_file') === $name) {
            return false;
        }

        $model->addMedia($file)->preservingOriginal()
            ->withCustomProperties(['catalogue_file' => $name])
            ->toMediaCollection($collection);

        return true;
    }

    private function uniqueProducerSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'producer';
        $slug = $base;

        for ($i = 2; Producer::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
