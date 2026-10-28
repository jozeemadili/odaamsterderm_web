<?php

namespace App\Services;

use App\Models\AnimalType;
use App\Models\InventoryItem;
use App\Models\LifeStage;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SearchKeyword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Loads the catalogue from the Oda/Amsterdam taxonomy workbook: animal
 * types, life stages, categories, the attribute dictionary with its allowed
 * values, the search keyword dictionary, brands (as producers), products
 * and their variants (pack sizes / SKUs).
 *
 * Rows are matched on their workbook code (AN-CHK, C1010, LS-POU-GRW, BR-SIL,
 * P001, P001-25KG…), so re-running updates in place. On a database that still has the
 * original category tree, the first run also moves products off the retired
 * Feed › Starter / Grower / Finisher / Layers Mash categories onto
 * Feed › Complete feed and records the stage as a life stage. Original
 * catalogue products listed in Map_Live_Products take over their workbook
 * product's code instead of being duplicated, and their pack size becomes
 * the workbook SKU, so existing stock keeps pointing at the right pack.
 */
class CatalogTaxonomyImporter
{
    public const DEFAULT_PATH = 'database/data/oda-taxonomy.xlsx';

    private const SHEETS = [
        'Animal_Types', 'Life_Stages', 'Categories', 'Attributes',
        'Attribute_Values', 'Search_Keywords', 'Brands', 'Products', 'Variants', 'Map_Live_Products',
    ];

    /** Administration route value meaning "none", left out of the stored list. */
    private const NOT_APPLICABLE = 'not_applicable';

    /** Original animal slugs whose workbook slug differs. */
    private const LEGACY_ANIMALS = [
        'other-livestock-animals' => 'AN-OTH',
    ];

    /** Original categories that carry on under a workbook code (same row, new name). */
    private const LEGACY_KEPT_CATEGORIES = [
        'feed' => 'C10',
        'feed-concentrates' => 'C1020',
        'vitamins-supplements' => 'C20',
        'medicines' => 'C30',
        'vaccines' => 'C40',
        'equipment' => 'C60',
        'equipment-feeders-drinkers' => 'C6010',
    ];

    /**
     * Original categories that are retired: products move to the new
     * category and, for the feed stages, get the stage as a life stage
     * of their animal group.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const LEGACY_RETIRED_CATEGORIES = [
        'feed-starter' => ['C1010', 'starter'],
        'feed-grower' => ['C1010', 'grower'],
        'feed-finisher' => ['C1010', 'finisher'],
        'feed-layers-mash' => ['C1010', 'laying'],
        'equipment-housing-brooding' => ['C6020', null],
        'veterinary-products' => ['C50', null],
    ];

    /** @var array<string, array<string, list<array<string, mixed>>>> parsed workbooks, keyed by path + mtime */
    private static array $parsed = [];

    public static function defaultPath(): string
    {
        return base_path(self::DEFAULT_PATH);
    }

    /**
     * @param  bool  $products  false loads the taxonomy only, without brands and products (the demo seeders add their own)
     * @return array<string, int> rows imported per table, plus products moved off retired categories
     */
    public function import(?string $path = null, bool $products = true): array
    {
        $sheets = $this->read($path ?? self::defaultPath());

        return DB::transaction(function () use ($sheets, $products) {
            $legacyProducts = $this->legacyCategoryProducts();

            $counts = [
                'animal_types' => $this->importAnimalTypes($sheets['Animal_Types']),
                'life_stages' => $this->importLifeStages($sheets['Life_Stages']),
                'product_categories' => $this->importCategories($sheets['Categories']),
                'product_attributes' => $this->importAttributes($sheets['Attributes']),
                'product_attribute_values' => $this->importAttributeValues($sheets['Attribute_Values']),
                'search_keywords' => $this->importSearchKeywords($sheets['Search_Keywords']),
            ];

            $counts['products_relinked'] = $this->relinkLegacyProducts($legacyProducts, $sheets['Map_Live_Products']);

            if ($products) {
                $counts['producers'] = $this->importProducers($sheets['Brands']);
                $counts += $this->importProducts($sheets['Products'], $sheets['Variants'], $sheets['Map_Live_Products']);
            }

            return $counts;
        });
    }

    /**
     * Each sheet as a list of rows keyed by the header row; blank rows dropped.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Taxonomy workbook not found: {$path}");
        }

        $cacheKey = $path.'@'.filemtime($path);

        if (isset(self::$parsed[$cacheKey])) {
            return self::$parsed[$cacheKey];
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(self::SHEETS);
        $workbook = $reader->load($path);

        $sheets = [];

        foreach (self::SHEETS as $name) {
            $sheet = $workbook->getSheetByName($name)
                ?? throw new RuntimeException("Taxonomy workbook has no '{$name}' sheet.");

            $rows = $sheet->toArray(null, false, false, false);
            $header = array_map(fn ($h) => trim((string) $h), array_shift($rows) ?? []);

            $sheets[$name] = array_values(array_filter(
                array_map(fn (array $row) => array_combine($header, array_map($this->clean(...), $row)), $rows),
                fn (array $row) => ($row[$header[0]] ?? null) !== null,
            ));
        }

        return self::$parsed[$cacheKey] = $sheets;
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAnimalTypes(array $rows): int
    {
        // Parents first, so children can point at them.
        usort($rows, fn ($a, $b) => ($a['parent_id'] !== null) <=> ($b['parent_id'] !== null));

        foreach ($rows as $sort => $row) {
            $animal = AnimalType::where('code', $row['animal_id'])->first()
                ?? AnimalType::whereNull('code')->where('slug', $row['slug'])->first()
                ?? AnimalType::whereNull('code')->whereIn('slug', array_keys(self::LEGACY_ANIMALS, $row['animal_id'], true))->first()
                ?? new AnimalType(['is_active' => true]);

            $animal->fill([
                'code' => $row['animal_id'],
                'parent_id' => $row['parent_id'] ? AnimalType::where('code', $row['parent_id'])->value('id') : null,
                'slug' => $row['slug'],
                'name' => $row['name_en'],
                'name_sw' => $row['name_sw'],
                'description' => $row['notes'],
                'sort_order' => $animal->exists ? $animal->sort_order : $sort,
            ])->save();
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importLifeStages(array $rows): int
    {
        $animalIds = AnimalType::whereNotNull('code')->pluck('id', 'code');

        foreach ($rows as $row) {
            LifeStage::updateOrCreate(['code' => $row['life_stage_id']], [
                'animal_type_id' => $row['applies_to_animal_id'] === '*' ? null : $animalIds[$row['applies_to_animal_id']],
                'slug' => $row['slug'],
                'name' => $row['name_en'],
                'name_sw' => $row['name_sw'],
                'description' => $row['notes'],
                'sort_order' => (int) $row['sort_order'],
            ]);
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importCategories(array $rows): int
    {
        foreach (self::LEGACY_KEPT_CATEGORIES as $slug => $code) {
            if (! ProductCategory::where('code', $code)->exists()) {
                ProductCategory::whereNull('code')->where('slug', $slug)->update(['code' => $code]);
            }
        }

        usort($rows, fn ($a, $b) => (int) $a['level'] <=> (int) $b['level']);

        foreach ($rows as $sort => $row) {
            $category = ProductCategory::where('code', $row['category_id'])->first()
                ?? ProductCategory::whereNull('code')->where('slug', $row['slug'])->first()
                ?? new ProductCategory(['is_active' => true]);

            $category->fill([
                'code' => $row['category_id'],
                'parent_id' => $row['parent_id'] ? ProductCategory::where('code', $row['parent_id'])->value('id') : null,
                'slug' => $row['slug'],
                'name' => $row['name_en'],
                'name_sw' => $row['name_sw'],
                'description' => $row['includes'],
                'tie_break_rule' => $row['excludes_tie_break'],
                'required_attributes' => $this->list($row['extra_required_attributes'], ','),
                'sort_order' => $sort,
            ])->save();
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAttributes(array $rows): int
    {
        foreach ($rows as $sort => $row) {
            ProductAttribute::updateOrCreate(['key' => $row['attribute_key']], [
                'label' => $row['label_en'],
                'label_sw' => $row['label_sw'],
                'level' => $row['level'],
                'data_type' => $row['data_type'],
                'is_multi_select' => strtoupper((string) $row['multi_select']) === 'Y',
                'required_for' => $row['required_for'],
                'values_source' => $row['allowed_values_source'],
                'notes' => $row['notes'],
                'sort_order' => $sort,
            ]);
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importAttributeValues(array $rows): int
    {
        $attributeIds = ProductAttribute::pluck('id', 'key');

        foreach ($rows as $sort => $row) {
            $attributeId = $attributeIds[$row['attribute_key']]
                ?? throw new RuntimeException("Attribute_Values uses unknown attribute '{$row['attribute_key']}'.");

            ProductAttributeValue::updateOrCreate(
                ['product_attribute_id' => $attributeId, 'code' => (string) $row['value_code']],
                ['label' => $row['label_en'], 'label_sw' => $row['label_sw'], 'sort_order' => $sort],
            );
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function importSearchKeywords(array $rows): int
    {
        foreach ($rows as $row) {
            SearchKeyword::updateOrCreate(
                ['term' => Str::lower($row['term']), 'target_type' => $row['maps_to_type'], 'target_code' => (string) $row['maps_to_id']],
                ['language' => $row['language'] ?? 'sw', 'notes' => $row['notes']],
            );
        }

        return count($rows);
    }

    /**
     * Brands become producers: the workbook's brand and producer are the
     * same company on every product. Uncoded producers are matched on name.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importProducers(array $rows): int
    {
        foreach ($rows as $row) {
            $producer = Producer::where('code', $row['brand_id'])->first()
                ?? Producer::whereNull('code')->where('name', $row['name'])->first()
                ?? new Producer(['is_active' => true]);

            $producer->slug ??= $this->uniqueSlug(Producer::class, $row['name']);
            $producer->fill([
                'code' => $row['brand_id'],
                'name' => $row['name'],
                'role' => $row['role'],
                'country' => $row['country'],
                'is_demo' => $this->bool($row['is_demo']),
            ])->save();
        }

        return count($rows);
    }

    /**
     * Products with their animals, life stages, attributes and variants.
     * The product's own unit and pack size summarise its variants: the
     * first variant's selling unit, and a pack size only when there is one.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array<string, mixed>>  $mapRows
     * @return array{products: int, product_variants: int, live_products_linked: int}
     */
    private function importProducts(array $rows, array $variantRows, array $mapRows): array
    {
        $animalIds = AnimalType::whereNotNull('code')->pluck('id', 'code');
        $categoryIds = ProductCategory::whereNotNull('code')->pluck('id', 'code');
        $stageIds = LifeStage::pluck('id', 'code');
        $producerIds = Producer::whereNotNull('code')->pluck('id', 'code');
        $producerNames = Producer::whereNotNull('code')->pluck('name', 'id');
        $variants = collect($variantRows)->groupBy('product_id');
        [$liveProducts, $liveSkus] = $this->liveProductsByCode($mapRows);
        $ids = fn (?string $codes, $lookup, string $what) => collect($this->list($codes, '|'))
            ->map(fn ($code) => $lookup[$code] ?? throw new RuntimeException("Product row uses unknown {$what} '{$code}'."))
            ->all();

        foreach ($rows as $row) {
            $code = $row['product_id'];
            $animals = $ids($row['animal_types'], $animalIds, 'animal type');
            $product = Product::where('code', $code)->first()
                ?? $liveProducts[$code]
                ?? new Product(['status' => Product::STATUS_APPROVED, 'reviewed_at' => now(), 'is_demo' => $this->bool($row['is_demo_data'])]);

            $productVariants = $variants[$code] ?? collect();
            $product->slug ??= Product::uniqueSlug($row['product_name'].' '.$row['brand_name']);
            $product->unit = $productVariants->first()['selling_unit'] ?? $product->unit ?? 'piece';
            $product->pack_size = $productVariants->count() === 1 ? $productVariants->first()['variant_name'] : null;

            $product->fill([
                'code' => $code,
                'name' => $row['product_name'],
                'description' => $row['description_en'],
                'product_category_id' => $categoryIds[$row['category_id']] ?? throw new RuntimeException("Product {$code} uses unknown category '{$row['category_id']}'."),
                'producer_id' => $producerId = $producerIds[$row['producer_id'] ?? $row['brand_id']] ?? null,
                'brand' => $producerId ? $producerNames[$producerId] : $row['brand_name'],
                'animal_type_id' => $animals[0] ?? throw new RuntimeException("Product {$code} has no animal type."),
                'regulatory_class' => $row['regulatory_class'],
                'prescription_required' => $this->bool($row['prescription_required']),
                'cold_chain_required' => $this->bool($row['cold_chain_required']),
                'production_purpose' => $this->list($row['production_purpose'], '|') ?: null,
                'use_cases' => $this->list($row['use_cases'], '|') ?: null,
                'disease_target' => $this->list($row['disease_target'], '|') ?: null,
                'administration_route' => array_values(array_diff($this->list($row['administration_route'], '|'), [self::NOT_APPLICABLE])) ?: null,
                'form' => $row['form'],
                'active_ingredients' => $this->text($row['active_ingredients']),
                'crude_protein_pct' => $row['crude_protein_pct'],
                'inclusion_or_dosage' => $this->text($row['inclusion_or_dosage']),
                'withdrawal_meat_days' => $row['withdrawal_meat_days'],
                'withdrawal_eggs_days' => $row['withdrawal_eggs_days'],
                'withdrawal_milk_days' => $row['withdrawal_milk_days'],
                'storage' => $this->text($row['storage']),
                'registration_no' => $this->text($row['registration_no']),
                'capacity' => $this->text($row['capacity']),
                'power_source' => $row['power_source'],
                'breed_strain' => $this->text($row['breed_strain']),
                'country_of_origin' => $row['country_of_origin'],
                'search_keywords' => $row['search_keywords'] ? Str::lower($row['search_keywords']) : null,
            ])->save();

            $product->animalTypes()->sync($animals);
            $product->lifeStages()->sync($ids($row['life_stages'], $stageIds, 'life stage'));
            $this->importVariants($product, $productVariants, $liveSkus[$code] ?? null);
        }

        return ['products' => count($rows), 'product_variants' => count($variantRows), 'live_products_linked' => count($liveProducts)];
    }

    /**
     * A product's variants, matched on SKU. A variant without a SKU yet (the
     * pack size a product was typed in with) is claimed by the workbook row
     * with the same pack, or by the SKU Map_Live_Products names for it.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function importVariants(Product $product, Collection $rows, ?string $liveSku): void
    {
        $unclaimed = $product->variants()->whereNull('sku')->get();

        foreach ($rows->values() as $sort => $row) {
            $packKeys = [
                ProductVariant::packKey($this->number($row['pack_size_value']).$row['pack_size_unit']),
                ProductVariant::packKey($row['variant_name']),
            ];

            $variant = ProductVariant::where('sku', $row['sku'])->first()
                ?? $unclaimed->first(fn (ProductVariant $v) => in_array(ProductVariant::packKey($v->name), $packKeys, true))
                ?? ($row['sku'] === $liveSku ? $unclaimed->first() : null)
                ?? new ProductVariant;
            $unclaimed = $unclaimed->reject(fn (ProductVariant $v) => $v->is($variant));

            $variant->fill([
                'product_id' => $product->id,
                'sku' => $row['sku'],
                'name' => $row['variant_name'],
                'pack_size_value' => $row['pack_size_value'],
                'pack_size_unit' => $row['pack_size_unit'],
                'selling_unit' => $row['selling_unit'],
                'net_weight_kg' => $row['net_weight_kg'],
                'gtin' => $this->text($row['gtin']),
                'reference_price' => $row['reference_price_tzs'],
                'min_order_qty' => (int) ($row['min_order_qty'] ?? 1),
                'sort_order' => $sort,
            ])->save();

            // Stock of the original product that never had a pack recorded.
            if ($row['sku'] === $liveSku) {
                InventoryItem::where('product_id', $product->id)->whereNull('product_variant_id')
                    ->update(['product_variant_id' => $variant->id]);
            }
        }
    }

    /**
     * Original catalogue products (no code yet) found in Map_Live_Products,
     * keyed by the workbook product they become, and the SKU their pack
     * size becomes.
     *
     * @param  list<array<string, mixed>>  $mapRows
     * @return array{0: array<string, Product>, 1: array<string, string>}
     */
    private function liveProductsByCode(array $mapRows): array
    {
        $products = [];
        $skus = [];

        foreach (Product::whereNull('code')->with('producer')->get() as $product) {
            if (($row = $this->liveProductMapping($product, $mapRows)) && ! isset($products[$row['new_product_id']])) {
                $products[$row['new_product_id']] = $product;
                $skus[$row['new_product_id']] = $row['new_sku'];
            }
        }

        return [$products, $skus];
    }

    /**
     * Products still sitting on an original (uncoded) category, with that
     * category's slug — captured before the import assigns codes.
     *
     * @return array<int, string> product id => original category slug
     */
    private function legacyCategoryProducts(): array
    {
        $legacySlugs = [...array_keys(self::LEGACY_KEPT_CATEGORIES), ...array_keys(self::LEGACY_RETIRED_CATEGORIES)];

        return Product::query()
            ->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->whereNull('product_categories.code')
            ->whereIn('product_categories.slug', $legacySlugs)
            ->pluck('product_categories.slug', 'products.id')
            ->all();
    }

    /**
     * Moves products found on original categories to their new category and
     * life stages: the workbook's Map_Live_Products row when the product is
     * one of the original catalogue products, otherwise the general rule for
     * its old category. Retired categories are deleted once empty.
     *
     * @param  array<int, string>  $legacyProducts
     * @param  list<array<string, mixed>>  $mapRows
     */
    private function relinkLegacyProducts(array $legacyProducts, array $mapRows): int
    {
        $categoryIds = ProductCategory::whereNotNull('code')->pluck('id', 'code');
        $stageIds = LifeStage::pluck('id', 'code');
        $moved = 0;

        foreach (Product::with(['animalType', 'producer'])->findMany(array_keys($legacyProducts)) as $product) {
            $oldSlug = $legacyProducts[$product->id];
            $mapped = $this->liveProductMapping($product, $mapRows);

            if ($mapped) {
                $categoryCode = $mapped['new_category_id'];
                $stageCodes = $this->list($mapped['new_life_stages'], '|');
            } elseif (isset(self::LEGACY_RETIRED_CATEGORIES[$oldSlug])) {
                [$categoryCode, $stageSlug] = self::LEGACY_RETIRED_CATEGORIES[$oldSlug];
                $stageCodes = $stageSlug ? array_filter([$this->stageCodeFor($product, $stageSlug)]) : [];
            } else {
                continue;
            }

            $product->update(['product_category_id' => $categoryIds[$categoryCode]]);
            $product->lifeStages()->syncWithoutDetaching(
                collect($stageCodes)->map(fn ($code) => $stageIds[$code] ?? null)->filter()->all()
            );
            $moved++;
        }

        ProductCategory::whereNull('code')
            ->whereIn('slug', array_keys(self::LEGACY_RETIRED_CATEGORIES))
            ->whereDoesntHave('products')
            ->delete();

        return $moved;
    }

    /**
     * The Map_Live_Products row for an original catalogue product, matched on
     * "name pack" (e.g. "Chicken Grower 25KG") and producer. A product with
     * no producer also matches when only one row has its name.
     *
     * @param  list<array<string, mixed>>  $mapRows
     * @return array<string, mixed>|null
     */
    private function liveProductMapping(Product $product, array $mapRows): ?array
    {
        $liveName = Str::lower(trim($product->name.' '.$product->pack_size));
        $sameName = array_values(array_filter($mapRows, fn ($row) => Str::lower($row['live_product_name']) === $liveName));

        foreach ($sameName as $row) {
            if (Str::lower((string) $row['live_producer']) === Str::lower((string) $product->producer?->name)) {
                return $row;
            }
        }

        return ! $product->producer_id && count($sameName) === 1 ? $sameName[0] : null;
    }

    /** The life stage code with this slug for the product's animal group (LS-POU-GRW, LS-PIG-GRW…). */
    private function stageCodeFor(Product $product, string $stageSlug): ?string
    {
        $animal = $product->animalType;
        $groupId = $animal?->parent_id ?? $animal?->id;

        return LifeStage::where('slug', $stageSlug)->where('animal_type_id', $groupId)->value('code');
    }

    /** @param  class-string<Model>  $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;

        for ($i = 2; $model::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** 25.0 → "25", 0.5 → "0.5" */
    private function number(mixed $value): string
    {
        return is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') : (string) $value;
    }

    private function bool(mixed $value): bool
    {
        return $value === true || strtoupper((string) $value) === 'TRUE';
    }

    /** Free-text cells can come back as numbers ("25" for a capacity). */
    private function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** @return list<string> */
    private function list(?string $value, string $separator): array
    {
        return array_values(array_filter(array_map('trim', explode($separator, (string) $value))));
    }
}
