<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\Producers\Index;
use App\Livewire\Admin\Producers\Show;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProducerPagesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $admin;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
        $this->producer = Producer::create(['name' => 'Shamba Pharma', 'code' => 'BR-SHP', 'country' => 'Tanzania']);
        Product::where('name', 'Chicken Starter')->update(['producer_id' => $this->producer->id]);
    }

    public function test_cards_link_to_view_more(): void
    {
        Livewire::actingAs($this->admin)->test(Index::class)
            ->assertSee('View more')
            ->assertSee(route('admin.producers.show', $this->producer), false);
    }

    public function test_view_more_page_shows_products_and_manages_logo_and_details(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Show::class, ['producer' => $this->producer])
            ->assertSee('Shamba Pharma')
            ->assertSee('Chicken Starter')
            ->assertSee('By category')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->assertSee('Logo saved');
        $this->assertNotNull($this->producer->fresh()->logoUrl());

        $page->set('editing', true)
            ->set('details.role', 'Manufacturer (veterinary medicines)')
            ->set('details.website', 'not a link')
            ->call('saveDetails')->assertHasErrors('details.website')
            ->set('details.website', 'https://shamba.example')
            ->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('Manufacturer (veterinary medicines)', $this->producer->fresh()->role);

        $page->call('toggleMarketplace');
        $this->assertFalse($this->producer->fresh()->on_marketplace);

        $page->set('products', 'verified')->assertDontSee('Chicken Starter');
    }

    public function test_a_hatchery_shows_its_chick_section(): void
    {
        $this->producer->update(['deals_in' => Producer::DEALS_CHICKS]);

        $this->actingAs($this->admin)->get(route('admin.producers.show', $this->producer))
            ->assertOk()->assertSee('Day-old chicks')->assertSee('Open order days');
    }
}
