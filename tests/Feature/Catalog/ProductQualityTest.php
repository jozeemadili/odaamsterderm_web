<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Models\AnimalType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ProductRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductQualityTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
    }

    /** A dewormer for chickens and dairy cows, complete except what the test leaves out. */
    private function dewormer(array $overrides = []): Product
    {
        $product = Product::create($overrides + [
            'name' => 'Albenda Plus', 'brand' => 'Shamba Pharma', 'unit' => 'bottle', 'pack_size' => '100ml',
            'animal_type_id' => AnimalType::where('code', 'AN-CHK')->value('id'),
            'product_category_id' => ProductCategory::where('code', 'C3020')->value('id'),
            'status' => Product::STATUS_APPROVED,
            'active_ingredients' => 'Albendazole 10%', 'inclusion_or_dosage' => '1 ml per 10 kg body weight',
            'administration_route' => ['oral_drench'], 'registration_no' => 'TVLA/123',
            'withdrawal_meat_days' => 14, 'withdrawal_eggs_days' => 7, 'withdrawal_milk_days' => 3,
        ]);
        $product->animalTypes()->syncWithoutDetaching([AnimalType::where('code', 'AN-DCT')->value('id')]);
        $product->syncDefaultVariant();
        $product->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('image');

        return $product->fresh();
    }

    public function test_medicines_need_withdrawal_periods_for_the_animals_they_treat(): void
    {
        $complete = $this->dewormer();
        $this->assertSame([], ProductRequirements::missing($complete));

        $noMilk = $this->dewormer(['name' => 'Albenda Lite', 'withdrawal_milk_days' => null, 'registration_no' => null]);
        $this->assertSame(['TVLA registration no.', 'Milk withdrawal (days)'], ProductRequirements::missing($noMilk));

        // Zero days is an answer, not a gap.
        $zero = $this->dewormer(['name' => 'Albenda Zero', 'withdrawal_eggs_days' => 0]);
        $this->assertNotContains('Egg withdrawal (days)', ProductRequirements::missing($zero));
    }

    public function test_score_and_missing_count_are_stored_for_sorting(): void
    {
        $product = $this->dewormer(['withdrawal_meat_days' => null]);
        $check = $product->refreshQuality();

        $this->assertSame(1, $product->fresh()->missing_required);
        $this->assertSame($check['score'], $product->fresh()->completeness);
        $this->assertLessThan(100, $check['score']);

        $this->artisan('catalog:quality')->assertSuccessful();
    }

    public function test_only_complete_products_can_be_verified_and_editing_a_label_fact_clears_it(): void
    {
        $incomplete = $this->dewormer(['registration_no' => null]);
        try {
            $incomplete->markVerified($this->admin);
            $this->fail('An incomplete product was verified.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('TVLA registration no.', collect($e->errors())->flatten()->first());
        }

        $product = $this->dewormer(['name' => 'Albenda Max']);
        $product->markVerified($this->admin, 'producer');
        $this->assertTrue($product->fresh()->isVerified());
        $this->assertSame('producer', $product->fresh()->verified_source);

        // A new description keeps the mark; a new dosage needs checking again.
        $product->update(['description' => 'Broad-spectrum dewormer.']);
        $this->assertTrue($product->fresh()->isVerified());
        $product->update(['inclusion_or_dosage' => '2 ml per 10 kg']);
        $this->assertFalse($product->fresh()->isVerified());
    }

    public function test_admin_sees_what_is_missing_filters_and_verifies(): void
    {
        $gap = $this->dewormer(['name' => 'Albenda Gap', 'withdrawal_eggs_days' => null]);
        $gap->refreshQuality();
        $ok = $this->dewormer(['name' => 'Albenda Good']);
        $ok->refreshQuality();

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->set('status', 'all')
            ->set('quality', 'missing')
            ->assertSee('Albenda Gap')
            ->assertDontSee('Albenda Good')
            ->call('startEditing', $gap->id)
            ->assertSee('Egg withdrawal (days)')
            ->call('verify', $gap->id)
            ->assertSee('Fill these first')
            ->call('verify', $ok->id, 'label');

        $this->assertTrue($ok->fresh()->isVerified());
        $this->assertFalse($gap->fresh()->isVerified());
    }

    public function test_buyers_see_the_verified_badge(): void
    {
        $product = $this->dewormer();
        $product->markVerified($this->admin);

        $this->get('/products/'.$product->slug)->assertOk()->assertSee('Verified details');
        $this->getJson('/api/v1/market/products/'.$product->id)->assertOk()->assertJsonPath('product.verified', true);
    }
}
