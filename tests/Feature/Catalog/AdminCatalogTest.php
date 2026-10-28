<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\AnimalTypes\Index as AnimalTypesIndex;
use App\Livewire\Admin\ProductCategories\Index as CategoriesIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Models\AnimalType;
use App\Models\LifeStage;
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

    public function test_admin_adds_sub_category_with_kiswahili_name(): void
    {
        $medicines = ProductCategory::where('code', 'C30')->first();

        Livewire::actingAs($this->admin)->test(CategoriesIndex::class)
            ->call('startCreating', $medicines->id)
            ->set('name', 'Pond Treatments')
            ->set('name_sw', 'Dawa za mabwawa')
            ->set('description', 'Water treatments for fish ponds.')
            ->call('save')
            ->assertHasNoErrors();

        $category = ProductCategory::where('name', 'Pond Treatments')->first();
        $this->assertSame($medicines->id, $category->parent_id);
        $this->assertSame('Dawa za mabwawa', $category->name_sw);
        $this->assertSame('Water treatments for fish ponds.', $category->description);
    }

    public function test_browse_categories_for_an_animal_are_those_with_its_products(): void
    {
        $fishIds = fn () => ProductCategory::withProductsFor(AnimalType::where('slug', 'fish-aquaculture')->value('id'))->pluck('code')->all();

        // Tilapia Floating Feed is tagged Tilapia, a child of Fish.
        $this->assertEqualsCanonicalizing(['C10', 'C1010'], $fishIds());

        Product::create([
            'name' => 'Tilapia Fingerlings', 'slug' => 'tilapia-fingerlings', 'unit' => 'piece',
            'animal_type_id' => AnimalType::where('slug', 'tilapia')->value('id'),
            'product_category_id' => ProductCategory::where('code', 'C7020')->value('id'),
            'status' => Product::STATUS_APPROVED,
        ]);

        $this->assertEqualsCanonicalizing(['C10', 'C1010', 'C70', 'C7020'], $fishIds());
    }

    public function test_admin_created_product_is_approved_and_any_category_fits_any_animal(): void
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

        // No hand-kept animal/category list: a fish vaccine is allowed.
        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->call('startCreating')
            ->set('form.name', 'Fish Vaccine')
            ->set('form.unit', 'vial')
            ->set('form.animal_type_id', (string) $fish->id)
            ->set('form.product_category_id', (string) $vaccines->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($vaccines->id, Product::where('name', 'Fish Vaccine')->value('product_category_id'));
    }

    public function test_admin_tags_several_animals_life_stages_and_sale_rules(): void
    {
        $cattle = AnimalType::where('slug', 'cattle')->first();
        $goatsSheep = AnimalType::where('slug', 'goats-sheep')->first();
        $allStages = LifeStage::where('code', 'LS-ALL')->first();
        $antibiotics = ProductCategory::where('code', 'C3010')->first();

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->call('startCreating')
            ->set('form.name', 'Penstrep Injection')
            ->set('form.unit', 'bottle')
            ->set('form.animal_type_id', (string) $cattle->id)
            ->set('form.animal_type_ids', [(string) $goatsSheep->id])
            ->set('form.life_stage_ids', [(string) $allStages->id])
            ->set('form.product_category_id', (string) $antibiotics->id)
            ->set('form.regulatory_class', 'prescription_vet_medicine')
            ->set('form.prescription_required', true)
            ->set('form.withdrawal_meat_days', '21')
            ->set('form.search_keywords', 'Sindano|Penstrep')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Penstrep Injection')->first();
        $this->assertEqualsCanonicalizing([$cattle->id, $goatsSheep->id], $product->animalTypes->pluck('id')->all());
        $this->assertSame([$allStages->id], $product->lifeStages->pluck('id')->all());
        $this->assertTrue($product->prescription_required);
        $this->assertSame(21, $product->withdrawal_meat_days);
        $this->assertSame('sindano|penstrep', $product->search_keywords);

        // Goat farmers find it, and the Kiswahili search word works.
        $this->assertTrue(Product::forAnimal(AnimalType::where('slug', 'goats')->value('id'))->whereKey($product->id)->exists());
        $this->assertTrue(Product::where('search_keywords', 'like', '%sindano%')->whereKey($product->id)->exists());

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->set('form.regulatory_class', 'not-a-class')
            ->call('startCreating')
            ->set('form.name', 'Bad')
            ->set('form.unit', 'bottle')
            ->set('form.animal_type_id', (string) $cattle->id)
            ->set('form.product_category_id', (string) $antibiotics->id)
            ->set('form.regulatory_class', 'not-a-class')
            ->call('save')
            ->assertHasErrors('form.regulatory_class');
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
