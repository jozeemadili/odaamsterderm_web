<?php

namespace Database\Seeders;

use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The animal-centred category tree from the requirements (section 8).
 * Idempotent: safe to re-run on a live database — it only adds what is
 * missing and never overwrites admin edits.
 */
class AnimalCatalogSeeder extends Seeder
{
    /** Animal groups → leaf animal types. */
    private const ANIMALS = [
        'Poultry' => ['Chicken', 'Other Poultry'],
        'Cattle' => [],
        'Goats & Sheep' => [],
        'Pigs' => [],
        'Fish / Aquaculture' => [],
        'Other Livestock / Animals' => [],
    ];

    /** Product categories → sub-categories. */
    private const CATEGORIES = [
        'Feed' => ['Starter', 'Grower', 'Finisher', 'Layers Mash', 'Concentrates'],
        'Vaccines' => [],
        'Medicines' => [],
        'Vitamins & Supplements' => [],
        'Equipment' => ['Feeders & Drinkers', 'Housing & Brooding'],
        'Veterinary Products' => [],
    ];

    /** Categories each animal group can use (section 8). */
    private const ALLOWED = [
        'Poultry' => ['Feed', 'Vaccines', 'Medicines', 'Vitamins & Supplements', 'Equipment'],
        'Cattle' => ['Feed', 'Vaccines', 'Medicines', 'Vitamins & Supplements', 'Equipment'],
        'Goats & Sheep' => ['Feed', 'Vaccines', 'Medicines', 'Vitamins & Supplements', 'Equipment'],
        'Pigs' => ['Feed', 'Vaccines', 'Medicines', 'Vitamins & Supplements', 'Equipment'],
        'Fish / Aquaculture' => ['Feed', 'Medicines', 'Equipment'],
        'Other Livestock / Animals' => ['Feed', 'Veterinary Products', 'Equipment'],
    ];

    public function run(): void
    {
        $sort = 0;
        $animals = [];

        foreach (self::ANIMALS as $group => $children) {
            $parent = $this->animal($group, null, $sort++);
            $animals[$group] = $parent;

            foreach ($children as $i => $child) {
                $animals[$child] = $this->animal($child, $parent->id, $i);
            }
        }

        $sort = 0;
        $categories = [];

        foreach (self::CATEGORIES as $name => $children) {
            $parent = $this->category($name, null, $sort++);
            $categories[$name] = $parent;

            foreach ($children as $i => $child) {
                $categories[$name.' › '.$child] = $this->category($child, $parent->id, $i, $name);
            }
        }

        foreach (self::ALLOWED as $group => $allowed) {
            $ids = collect($allowed)->map(fn ($c) => $categories[$c]->id)->all();
            $animals[$group]->categories()->syncWithoutDetaching($ids);
        }

        $this->starterProducts($animals, $categories);
    }

    private function animal(string $name, ?int $parentId, int $sort): AnimalType
    {
        return AnimalType::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'parent_id' => $parentId, 'sort_order' => $sort, 'is_active' => true],
        );
    }

    private function category(string $name, ?int $parentId, int $sort, ?string $parentName = null): ProductCategory
    {
        return ProductCategory::firstOrCreate(
            ['slug' => Str::slug(($parentName ? $parentName.' ' : '').$name)],
            ['name' => $name, 'parent_id' => $parentId, 'sort_order' => $sort, 'is_active' => true],
        );
    }

    /**
     * A handful of common products so the marketplace has something to
     * compare on day one. Admins curate the rest.
     *
     * @param  array<string, AnimalType>  $animals
     * @param  array<string, ProductCategory>  $categories
     */
    private function starterProducts(array $animals, array $categories): void
    {
        $products = [
            ['Chicken Starter', '25KG', 'bag', 'Chicken', 'Feed › Starter', null],
            ['Chicken Grower', '25KG', 'bag', 'Chicken', 'Feed › Grower', null],
            ['Chicken Finisher', '25KG', 'bag', 'Chicken', 'Feed › Finisher', null],
            ['Layers Mash', '50KG', 'bag', 'Chicken', 'Feed › Layers Mash', null],
            ['Newcastle Disease Vaccine (Lasota)', '100 doses', 'vial', 'Chicken', 'Vaccines', null],
            ['Gumboro Vaccine', '1000 doses', 'vial', 'Chicken', 'Vaccines', null],
            ['Poultry Multivitamin', '100g', 'sachet', 'Poultry', 'Vitamins & Supplements', null],
            ['Chick Drinker', '4L', 'piece', 'Poultry', 'Equipment › Feeders & Drinkers', null],
            ['Dairy Meal', '70KG', 'bag', 'Cattle', 'Feed', null],
            ['Albendazole Dewormer', '1L', 'bottle', 'Cattle', 'Medicines', null],
            ['Oxytetracycline 20% Injection', '100ml', 'bottle', 'Cattle', 'Medicines', null],
            ['Goat & Sheep Mineral Lick', '2KG', 'piece', 'Goats & Sheep', 'Vitamins & Supplements', null],
            ['Pig Grower Pellets', '50KG', 'bag', 'Pigs', 'Feed › Grower', null],
            ['Tilapia Floating Feed', '25KG', 'bag', 'Fish / Aquaculture', 'Feed', null],
        ];

        foreach ($products as [$name, $pack, $unit, $animal, $category, $brand]) {
            Product::firstOrCreate(
                ['name' => $name, 'pack_size' => $pack],
                [
                    'slug' => Product::uniqueSlug($name.' '.$pack),
                    'animal_type_id' => $animals[$animal]->id,
                    'product_category_id' => $categories[$category]->id,
                    'brand' => $brand,
                    'unit' => $unit,
                    'status' => Product::STATUS_APPROVED,
                    'reviewed_at' => now(),
                ],
            );
        }
    }
}
