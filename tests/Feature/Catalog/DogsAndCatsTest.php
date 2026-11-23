<?php

namespace Tests\Feature\Catalog;

use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ProductRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class DogsAndCatsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_dogs_and_cats_are_animals_with_growth_stages(): void
    {
        $dogs = AnimalType::where('code', 'AN-DOG')->sole();
        $cats = AnimalType::where('code', 'AN-FEL')->sole();

        $this->assertNull($dogs->parent_id);
        $this->assertSame('Mbwa', $dogs->name_sw);
        $this->assertSame('Paka', $cats->name_sw);
        $this->assertSame(['LS-DOG-PUP', 'LS-DOG-ADL', 'LS-DOG-SNR', 'LS-DOG-BRD'], $dogs->lifeStages->pluck('code')->all());
        $this->assertSame(['LS-FEL-KIT', 'LS-FEL-ADL', 'LS-FEL-SNR', 'LS-FEL-BRD'], $cats->lifeStages->pluck('code')->all());
        $this->assertSame('🐕', $dogs->emoji());
    }

    public function test_running_the_migration_again_changes_nothing(): void
    {
        $migration = require database_path('migrations/2026_11_23_100001_add_dogs_and_cats_animal_types.php');
        $before = [AnimalType::count(), LifeStage::count()];

        $migration->up();

        $this->assertSame($before, [AnimalType::count(), LifeStage::count()]);
    }

    public function test_pet_medicines_need_no_withdrawal_period(): void
    {
        $product = Product::create([
            'name' => 'Pet Dewormer', 'brand' => 'Vet Co', 'unit' => 'box', 'pack_size' => '4 tablets',
            'animal_type_id' => AnimalType::where('code', 'AN-DOG')->value('id'),
            'product_category_id' => ProductCategory::where('code', 'C3020')->value('id'),
            'status' => Product::STATUS_PENDING,
        ]);
        $product->animalTypes()->syncWithoutDetaching([AnimalType::where('code', 'AN-FEL')->value('id')]);

        $missing = ProductRequirements::missing($product->fresh());

        $this->assertNotContains('Meat withdrawal (days)', $missing);
        $this->assertNotContains('Egg withdrawal (days)', $missing);
        $this->assertNotContains('Milk withdrawal (days)', $missing);
        $this->assertContains('Active ingredients', $missing);
    }
}
