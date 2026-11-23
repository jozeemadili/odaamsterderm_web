<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\ProductCategories\Index;
use App\Livewire\Admin\ProductCategories\Show;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CategoryPagesTest extends TestCase
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

    public function test_list_shows_cards_with_view_more_and_adds_with_a_photo_and_code(): void
    {
        $medicines = ProductCategory::where('code', 'C30')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee($medicines->name)
            ->assertSee('View more')
            ->assertSee(route('admin.product-categories.show', $medicines), false)
            ->call('startCreating', $medicines->id)
            ->set('name', 'Wound sprays')
            ->set('image', UploadedFile::fake()->image('spray.jpg'))
            ->call('save')->assertHasNoErrors();

        $new = ProductCategory::where('name', 'Wound sprays')->sole();
        $this->assertStringStartsWith('C30', $new->code);
        $this->assertNotNull($new->imageUrl());
    }

    public function test_main_category_page_manages_photo_details_and_subcategories(): void
    {
        $vaccines = ProductCategory::where('code', 'C30')->firstOrFail();
        $poultryVaccines = ProductCategory::where('code', 'C3020')->firstOrFail();

        $page = Livewire::actingAs($this->admin)->test(Show::class, ['productCategory' => $vaccines])
            ->assertSee('Subcategories of')
            ->assertSee('TVLA registration no.') // what its subcategories need
            ->set('photo', UploadedFile::fake()->image('vaccines.jpg'))
            ->assertSee('Photo saved');
        $this->assertNotNull($vaccines->fresh()->imageUrl());

        $page->set('editing', true)->set('details.name_sw', 'Dawa')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('Dawa', $vaccines->fresh()->name_sw);

        $page->set('subForm', true)->set('sub.name', 'Fish medicines')->call('saveSub')->assertHasNoErrors();
        $this->assertStringStartsWith('C30', ProductCategory::where('name', 'Fish medicines')->value('code'));

        $page->set("subPhotos.{$poultryVaccines->id}", UploadedFile::fake()->image('p.jpg'));
        $this->assertNotNull($poultryVaccines->fresh()->imageUrl());
    }

    public function test_subcategory_page_lists_what_its_products_must_have(): void
    {
        $dewormers = ProductCategory::where('code', 'C3020')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.product-categories.show', $dewormers))
            ->assertOk()
            ->assertSee('What a product here must have')
            ->assertSee('TVLA registration no.')
            ->assertSee('Egg withdrawal (days): for poultry');
    }
}
