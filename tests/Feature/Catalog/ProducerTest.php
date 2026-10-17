<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\Producers\Index as ProducersIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Models\AnimalType;
use App\Models\InventoryItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProducerTest extends TestCase
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

    public function test_admin_adds_a_producer_with_a_logo(): void
    {
        $this->actingAs($this->admin)->get(route('admin.producers.index'))->assertOk();

        $support = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $support->assignRole('support_agent');
        $this->actingAs($support)->get(route('admin.producers.index'))->assertForbidden();

        Livewire::actingAs($this->admin)->test(ProducersIndex::class)
            ->call('startCreating')
            ->set('name', 'Silverlands')
            ->set('logo', UploadedFile::fake()->image('silverlands.png', 128, 128))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Silverlands');

        $producer = Producer::where('name', 'Silverlands')->firstOrFail();
        $this->assertSame('silverlands', $producer->slug);
        $this->assertNotNull($producer->logoUrl());

        // Names are unique.
        Livewire::actingAs($this->admin)->test(ProducersIndex::class)
            ->call('startCreating')->set('name', 'Silverlands')->call('save')
            ->assertHasErrors('name');
    }

    public function test_linking_a_product_sets_its_brand_and_renames_follow(): void
    {
        $producer = Producer::create(['name' => 'Silverlands']);
        $chicken = AnimalType::where('slug', 'chicken')->first();
        $grower = ProductCategory::where('slug', 'feed-grower')->first();

        Livewire::actingAs($this->admin)->test(ProductsIndex::class)
            ->call('startCreating')
            ->set('form.name', 'Chicken Grower')
            ->set('form.pack_size', '25KG')
            ->set('form.unit', 'bag')
            ->set('form.producer_id', (string) $producer->id)
            ->set('form.animal_type_id', (string) $chicken->id)
            ->set('form.product_category_id', (string) $grower->id)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('producer_id', $producer->id)->firstOrFail();
        $this->assertSame('Silverlands', $product->brand);
        $this->assertSame('chicken-grower-25kg-silverlands', $product->slug);

        $producer->update(['name' => 'Silverlands Tanzania']);
        $this->assertSame('Silverlands Tanzania', $product->fresh()->brand);
    }

    public function test_customers_see_which_producer_makes_each_product(): void
    {
        $producer = Producer::create(['name' => 'Silverlands', 'country' => 'Tanzania']);
        Product::where('name', 'Chicken Starter')->first()->update(['producer_id' => $producer->id]);

        $this->get('/')->assertOk()->assertSeeInOrder(['Chicken Starter 25KG', 'by', 'Silverlands']);
        $this->get('/products/chicken-starter-25kg')->assertOk()->assertSee('Silverlands')->assertSee('Made in Tanzania');

        $this->getJson('/api/v1/market/products?search=starter')
            ->assertJsonPath('data.0.producer.name', 'Silverlands')
            ->assertJsonPath('data.0.producer.country', 'Tanzania');
        $this->getJson('/api/v1/market/products/'.Product::where('name', 'Chicken Starter')->value('id'))
            ->assertJsonPath('product.producer.name', 'Silverlands')
            ->assertJsonPath('offers.0.producer.name', 'Silverlands');
    }

    public function test_sellers_can_propose_a_product_for_a_listed_producer(): void
    {
        $producer = Producer::create(['name' => 'Silverlands']);
        Producer::create(['name' => 'Old Mill', 'is_active' => false]);
        $seller = User::where('merchant_id', InventoryItem::first()->merchant_id)->first()
            ?? User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => InventoryItem::first()->merchant_id]);
        $seller->assignRole('merchant_owner');
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/catalog/producers')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Silverlands');

        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Chicken Finisher',
            'pack_size' => '50KG',
            'unit' => 'bag',
            'producer_id' => $producer->id,
            'animal_type_id' => AnimalType::where('slug', 'chicken')->value('id'),
            'product_category_id' => ProductCategory::where('slug', 'feed-finisher')->value('id'),
        ])->assertCreated()
            ->assertJsonPath('data.producer.name', 'Silverlands')
            ->assertJsonPath('data.brand', 'Silverlands')
            ->assertJsonPath('data.status', 'pending');
    }
}
