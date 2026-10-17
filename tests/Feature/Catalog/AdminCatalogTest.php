<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\AnimalTypes\Index as AnimalTypesIndex;
use App\Livewire\Admin\ProductCategories\Index as CategoriesIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Models\AnimalType;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\AnimalCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, AnimalCatalogSeeder::class]);

        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
    }

    public function test_catalog_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()->assertSee('Chicken Starter 25KG');
        $this->actingAs($this->admin)->get(route('admin.animal-types.index'))->assertOk()->assertSee('Poultry');
        $this->actingAs($this->admin)->get(route('admin.product-categories.index'))->assertOk()->assertSee('Vaccines');
    }

    public function test_staff_without_permission_cannot_manage_catalog(): void
    {
        $support = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $support->assignRole('support_agent');

        $this->actingAs($support)->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_admin_adds_animal_type_under_a_group(): void
    {
        $poultry = AnimalType::where('slug', 'poultry')->first();

        Livewire::actingAs($this->admin)->test(AnimalTypesIndex::class)
            ->call('startCreating', $poultry->id)
            ->set('name', 'Ducks')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($poultry->id, AnimalType::where('name', 'Ducks')->first()->parent_id);
    }

    public function test_admin_restricts_category_to_animal_groups(): void
    {
        $fish = AnimalType::where('slug', 'fish-aquaculture')->first();

        Livewire::actingAs($this->admin)->test(CategoriesIndex::class)
            ->call('startCreating')
            ->set('name', 'Pond Treatments')
            ->set('animalTypeIds', [(string) $fish->id])
            ->call('save')
            ->assertHasNoErrors();

        $category = ProductCategory::where('name', 'Pond Treatments')->first();
        $this->assertEquals([$fish->id], $category->animalTypes->pluck('id')->all());
        $this->assertFalse(ProductCategory::forAnimalType(AnimalType::where('slug', 'cattle')->first()->id)->whereKey($category->id)->exists());
    }

    public function test_admin_created_product_is_approved_and_category_must_fit_animal(): void
    {
        $chicken = AnimalType::where('slug', 'chicken')->first();
        $vaccines = ProductCategory::where('slug', 'vaccines')->first();
        $fish = AnimalType::where('slug', 'fish-aquaculture')->first();

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->call('startCreating')
            ->set('form.name', 'Fowl Pox Vaccine')
            ->set('form.pack_size', '500 doses')
            ->set('form.unit', 'vial')
            ->set('form.animal_type_id', (string) $chicken->id)
            ->set('form.product_category_id', (string) $vaccines->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Product::where('name', 'Fowl Pox Vaccine')->first()->isApproved());

        // Fish have no vaccines category.
        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->call('startCreating')
            ->set('form.name', 'Fish Vaccine')
            ->set('form.unit', 'vial')
            ->set('form.animal_type_id', (string) $fish->id)
            ->set('form.product_category_id', (string) $vaccines->id)
            ->call('save')
            ->assertHasErrors('form.product_category_id');
    }

    public function test_admin_approves_and_rejects_seller_proposals(): void
    {
        $merchant = Merchant::create(['business_name' => 'Shamba Feeds', 'owner_name' => 'Juma', 'phone' => '+255711000000']);
        $base = [
            'animal_type_id' => AnimalType::where('slug', 'cattle')->first()->id,
            'product_category_id' => ProductCategory::where('slug', 'feed')->first()->id,
            'unit' => 'bag',
            'status' => Product::STATUS_PENDING,
            'proposed_by_merchant_id' => $merchant->id,
        ];
        $good = Product::create($base + ['name' => 'Calf Starter', 'pack_size' => '25KG']);
        $dup = Product::create($base + ['name' => 'Dairy Meal', 'pack_size' => '70KG']);

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->assertSet('status', 'pending')
            ->call('approve', $good->id)
            ->call('startReview', $dup->id)
            ->call('reject')
            ->assertHasErrors('reviewNotes')
            ->set('reviewNotes', 'Already in the catalog')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertTrue($good->fresh()->isApproved());
        $this->assertSame('rejected', $dup->fresh()->status);
        $this->assertSame('Already in the catalog', $dup->fresh()->review_notes);
    }
}
