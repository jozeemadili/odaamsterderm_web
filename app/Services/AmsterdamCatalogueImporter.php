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

/**
 * Loads Amsterdam Group's own product catalogue (database/data/
 * amsterdam-catalogue.json, typed in from their PDF catalogue) on top of
 * the taxonomy workbook: full descriptions, use rates, extra pack sizes and
 * the products the workbook lacks (Selko, Trouw Nutrition, OCP…).
 *
 * Rows are matched on their product code (P034 is the workbook's Vitamix
 * Chick, P116+ are new) and variants on their SKU, so re-running updates in
 * place and never touches stock. The catalogue's front and back pack photos
 * are the products' own photos, so customers see them on every card.
 */
class AmsterdamCatalogueImporter
{
    public const DATA_PATH = 'database/data/amsterdam-catalogue.json';

    public const PHOTO_DIR = 'database/data/amsterdam-catalogue';

    /**
     * @return array{producers: int, products: int, product_variants: int, photos_added: int}
     */
    public function import(?string $path = null): array
    {
        $path ??= base_path(self::DATA_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Catalogue file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! ProductCategory::whereNotNull('code')->exists()) {
            throw new RuntimeException('Import the taxonomy workbook first (php artisan catalog:import-taxonomy).');
        }

        return DB::transaction(function () use ($data, $path) {
            foreach ($data['producers'] as $row) {
                $this->importProducer($row);
            }

            $variants = $photos = 0;

            $photoDir = dirname($path).'/'.basename(self::PHOTO_DIR).'/';

            foreach ($data['products'] as $row) {
                $product = $this->importProduct($row);
                $variants += count($row['variants']);
                $photos += (int) $this->setPhoto($product, 'image', $photoDir.$row['photo']);
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

    /** @param  array<string, string>  $row */
    private function importProducer(array $row): void
    {
        $producer = Producer::where('code', $row['code'])->first()
            ?? Producer::whereNull('code')->where('name', $row['name'])->first()
            ?? new Producer(['is_active' => true]);

        $producer->slug ??= $this->uniqueProducerSlug($row['name']);
        $producer->fill([
            'code' => $row['code'],
            'name' => $row['name'],
            'role' => $row['role'],
            'country' => $row['country'],
            'is_demo' => false,
        ])->save();
    }

    /** @param  array<string, mixed>  $row */
    private function importProduct(array $row): Product
    {
        $code = $row['code'];
        // The maker on the pack, and who the product is shown under (the
        // catalogue owner, when one is configured).
        $maker = Producer::where('code', $row['producer'])->first()
            ?? throw new RuntimeException("Product {$code} uses unknown producer '{$row['producer']}'.");
        $producer = config('hub.catalogue_producer')
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
            'unit' => $row['variants'][0]['selling_unit'],
            'is_demo' => false,
        ], fn ($value) => $value !== null));
        $product->pack_size = count($row['variants']) === 1 ? $row['variants'][0]['name'] : null;
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
    private function setPhoto(Product $product, string $collection, string $file): bool
    {
        $current = $product->getFirstMedia($collection);
        $name = basename($file);

        if (! is_file($file) || $current?->getCustomProperty('catalogue_file') === $name) {
            return false;
        }

        $product->addMedia($file)->preservingOriginal()
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
