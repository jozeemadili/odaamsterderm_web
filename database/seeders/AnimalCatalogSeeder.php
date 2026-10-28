<?php

namespace Database\Seeders;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CatalogTaxonomyImporter;
use Illuminate\Database\Seeder;

/**
 * The catalogue taxonomy from the Oda/Amsterdam taxonomy workbook (animal
 * types, life stages, categories, attributes, search keywords, brands) plus
 * a handful of starter products. Safe to re-run: taxonomy rows are matched
 * on their workbook code, and existing products are never overwritten.
 * The workbook's 115 products load with `php artisan catalog:import-taxonomy`.
 */
class AnimalCatalogSeeder extends Seeder
{
    public function run(CatalogTaxonomyImporter $importer): void
    {
        $importer->import(products: false);

        $this->starterProducts();
    }

    /**
     * A handful of common products so the marketplace has something to
     * compare on day one. Admins curate the rest.
     */
    private function starterProducts(): void
    {
        $animals = AnimalType::pluck('id', 'slug');
        $categories = ProductCategory::whereNotNull('code')->pluck('id', 'code');
        $stages = LifeStage::pluck('id', 'code');

        $products = [
            ['Chicken Starter', '25KG', 'bag', 'chicken', 'C1010', ['LS-POU-STR']],
            ['Chicken Grower', '25KG', 'bag', 'chicken', 'C1010', ['LS-POU-GRW']],
            ['Chicken Finisher', '25KG', 'bag', 'chicken', 'C1010', ['LS-POU-FIN']],
            ['Layers Mash', '50KG', 'bag', 'chicken', 'C1010', ['LS-POU-LAY']],
            ['Newcastle Disease Vaccine (Lasota)', '100 doses', 'vial', 'chicken', 'C40', ['LS-ALL']],
            ['Gumboro Vaccine', '1000 doses', 'vial', 'chicken', 'C40', ['LS-POU-STR']],
            ['Poultry Multivitamin', '100g', 'sachet', 'poultry', 'C2010', ['LS-ALL']],
            ['Chick Drinker', '4L', 'piece', 'poultry', 'C6010', ['LS-ALL']],
            ['Dairy Meal', '70KG', 'bag', 'dairy-cattle', 'C1010', ['LS-CAT-LAC']],
            ['Albendazole Dewormer', '1L', 'bottle', 'cattle', 'C3020', ['LS-ALL']],
            ['Oxytetracycline 20% Injection', '100ml', 'bottle', 'cattle', 'C3010', ['LS-ALL']],
            ['Goat & Sheep Mineral Lick', '2KG', 'piece', 'goats-sheep', 'C2030', ['LS-ALL']],
            ['Pig Grower Pellets', '50KG', 'bag', 'pigs', 'C1010', ['LS-PIG-GRW']],
            ['Tilapia Floating Feed', '25KG', 'bag', 'tilapia', 'C1010', ['LS-FSH-GRO']],
        ];

        foreach ($products as [$name, $pack, $unit, $animal, $category, $lifeStages]) {
            $product = Product::firstOrCreate(
                ['name' => $name, 'pack_size' => $pack],
                [
                    'slug' => Product::uniqueSlug($name.' '.$pack),
                    'animal_type_id' => $animals[$animal],
                    'product_category_id' => $categories[$category],
                    'unit' => $unit,
                    'status' => Product::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ],
            );

            if ($product->wasRecentlyCreated) {
                $product->lifeStages()->attach(array_map(fn ($code) => $stages[$code], $lifeStages));
                $product->animalTypes()->syncWithoutDetaching([$product->animal_type_id]);
                $product->syncDefaultVariant();
            }
        }
    }
}
