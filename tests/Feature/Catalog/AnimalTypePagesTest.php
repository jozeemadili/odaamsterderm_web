<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\AnimalTypes\Index;
use App\Livewire\Admin\AnimalTypes\Show;
use App\Models\AnimalType;
use App\Models\LifeStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class AnimalTypePagesTest extends TestCase
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

    public function test_list_shows_friendly_cards_with_view_more(): void
    {
        $poultry = AnimalType::where('code', 'AN-POU')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee($poultry->shortName())
            ->assertSee('View more')
            ->assertSee(route('admin.animal-types.show', $poultry), false)
            ->set('search', 'kuku')
            ->assertSee($poultry->shortName());

        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('startCreating')
            ->set('name', 'Bees')->set('name_sw', 'Nyuki')->set('sort_order', '90')
            ->set('image', UploadedFile::fake()->image('bees.jpg'))
            ->call('save')->assertHasNoErrors();
        $this->assertNotNull(AnimalType::where('name', 'Bees')->sole()->imageUrl());
    }

    public function test_view_more_page_manages_photo_details_types_and_stages(): void
    {
        $poultry = AnimalType::where('code', 'AN-POU')->firstOrFail();

        $page = Livewire::actingAs($this->admin)->test(Show::class, ['animalType' => $poultry])
            ->assertSee('Growth stages')
            ->assertSee('Types of')
            ->set('photo', UploadedFile::fake()->image('poultry.jpg'))
            ->assertSee('Photo saved');
        $this->assertNotNull($poultry->fresh()->imageUrl());

        $page->set('editing', true)->set('details.name_sw', 'Ndege wafugwao')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('Ndege wafugwao', $poultry->fresh()->name_sw);

        $page->set('typeForm', true)->set('type.name', 'Quail')->call('saveType')->assertHasNoErrors();
        $this->assertSame($poultry->id, AnimalType::where('name', 'Quail')->value('parent_id'));

        $page->call('newStage')->set('stage.name', 'Brooding (week 1)')->set('stage.name_sw', 'Kuatamia')
            ->set('stagePhoto', UploadedFile::fake()->image('brood.jpg'))
            ->call('saveStage')->assertHasNoErrors();
        $stage = LifeStage::where('name', 'Brooding (week 1)')->sole();
        $this->assertSame($poultry->id, $stage->animal_type_id);
        $this->assertNotNull($stage->imageUrl());
        $this->assertStringStartsWith('LS-POU-', $stage->code);

        // A photo dropped straight on another stage's card.
        $other = $poultry->lifeStages()->where('id', '!=', $stage->id)->firstOrFail();
        $page->set("stagePhotos.{$other->id}", UploadedFile::fake()->image('grower.jpg'));
        $this->assertNotNull($other->fresh()->imageUrl());
    }

    public function test_a_type_page_shows_its_groups_stages(): void
    {
        $chicken = AnimalType::where('code', 'AN-CHK')->firstOrFail();
        $stage = $chicken->parent->lifeStages()->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.animal-types.show', $chicken))
            ->assertOk()
            ->assertSee($stage->name)
            ->assertSee('Stages are shared by all');
    }
}
