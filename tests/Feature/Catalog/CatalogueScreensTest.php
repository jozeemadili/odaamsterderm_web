<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Admin\ProductRequests\Index as AdminRequests;
use App\Livewire\Portal\Inventory\AddFromCatalogue;
use App\Livewire\Portal\ProductRequests\Index as PortalRequests;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiResponse;
use App\Services\ProductRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CatalogueScreensTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();
        $this->owner = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        $this->owner->assignRole('merchant_owner');
        $this->admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $this->admin->assignRole('super_admin');
    }

    private function request(): ProductRequest
    {
        return app(ProductRequests::class)->create($this->stores['A']->merchant, $this->owner, [
            'name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma', 'pack_size' => '100 g sachet',
        ], UploadedFile::fake()->image('front.jpg', 800, 1000), UploadedFile::fake()->image('back.jpg', 800, 1000),
            ['branch_id' => $this->stores['A']->id, 'unit_price' => 5000, 'quantity' => 12]);
    }

    public function test_agent_ticks_pack_sizes_and_adds_them_to_stock(): void
    {
        $product = Product::approved()->whereHas('variants')
            ->whereDoesntHave('inventoryItems', fn ($q) => $q->where('branch_id', $this->stores['A']->id))->firstOrFail();
        $variant = $product->variants()->first();

        Livewire::actingAs($this->owner)->test(AddFromCatalogue::class)
            ->set('branchId', (string) $this->stores['A']->id)
            ->assertSee($product->name)
            ->call('add')->assertHasErrors('lines')
            ->set("lines.{$variant->id}.on", true)
            ->call('add')->assertHasErrors('lines') // price and quantity needed
            ->set("lines.{$variant->id}.price", '9000')
            ->set("lines.{$variant->id}.qty", '6')
            ->call('add')->assertHasNoErrors()
            ->assertSee('1 added to');

        $item = InventoryItem::where('branch_id', $this->stores['A']->id)->where('product_variant_id', $variant->id)->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertEquals(6, $item->quantity_on_hand);
    }

    public function test_agent_requests_a_missing_product_with_label_photos(): void
    {
        $starter = $this->chickenStarter();

        Livewire::actingAs($this->owner)->test(PortalRequests::class)
            ->set('name', 'Chicken Starter')
            ->assertSee('Is it one of these?')
            ->assertSee($starter->name)
            ->set('name', 'Kuku Dawa Plus')
            ->set('producer_name', 'Shamba Pharma')
            ->set('pack_size', '100 g sachet')
            ->call('submit')->assertHasErrors(['front', 'back'])
            ->set('front', UploadedFile::fake()->image('front.jpg'))
            ->set('back', UploadedFile::fake()->image('back.jpg'))
            ->set('branch_id', (string) $this->stores['A']->id)
            ->set('unit_price', '5000')
            ->set('quantity', '10')
            ->call('submit')->assertHasNoErrors()
            ->assertSee('Request sent')
            ->assertSee('Waiting for review');

        $request = ProductRequest::sole();
        $this->assertNotNull($request->photoUrl('label_back'));
        $this->assertEquals(10, $request->inventoryItem->quantity_on_hand);
    }

    public function test_admin_builds_the_product_from_the_ai_draft_and_links_stock(): void
    {
        $request = $this->request();
        $this->app->instance(AiClient::class, new class implements AiClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse
            {
                return new AiResponse([
                    'name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma', 'category_code' => 'C2010',
                    'animal_codes' => ['AN-CHK'], 'life_stage_codes' => [], 'form' => 'powder',
                    'pack_sizes' => [['name' => '100 g sachet', 'value' => 100, 'unit' => 'g', 'selling_unit' => 'sachet']],
                    'description' => null, 'active_ingredients' => 'Vitamins A, D3, E', 'dosage' => '1 g per 2 L for 5 days',
                    'administration_route' => ['drinking_water'], 'use_cases' => [], 'disease_target' => [],
                    'withdrawal_meat_days' => 0, 'withdrawal_eggs_days' => null, 'withdrawal_milk_days' => null,
                    'registration_no' => null, 'storage' => null, 'prescription_required' => false, 'doubts' => null,
                ], 'fake');
            }
        });

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->assertSet('selected', $request->id)
            ->assertSee('Kuku Dawa Plus')
            ->call('draft')
            ->assertSet('form.category_code', 'C2010')
            ->assertSet('form.dosage', '1 g per 2 L for 5 days')
            ->call('approve')->assertHasNoErrors()
            ->assertSet('selected', null);

        $request->refresh();
        $this->assertSame(ProductRequest::APPROVED, $request->status);
        $this->assertSame($request->product_id, $request->inventoryItem->product_id);
    }

    public function test_admin_links_a_request_to_an_existing_product_or_declines_it(): void
    {
        $first = $this->request();
        $second = $this->request();
        $variant = $this->chickenStarter()->variants()->firstOrFail();

        Livewire::actingAs($this->admin)->test(AdminRequests::class)
            ->assertSet('selected', $first->id)
            ->call('link')->assertHasErrors('linkVariant')
            ->set('linkVariant', (string) $variant->id)
            ->call('link')
            ->assertSet('selected', $second->id) // moves on to the next one
            ->call('reject')->assertHasErrors('rejectReason')
            ->set('rejectReason', 'Photos are blurry')
            ->call('reject')->assertHasNoErrors();

        $this->assertSame(ProductRequest::LINKED, $first->fresh()->status);
        $this->assertSame($variant->id, $first->inventoryItem->fresh()->product_variant_id);
        $this->assertSame(ProductRequest::REJECTED, $second->fresh()->status);
    }

    public function test_only_catalogue_staff_open_the_review_queue(): void
    {
        $this->actingAs($this->admin)->get(route('admin.product-requests.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.product-requests.index'))->assertForbidden();
    }
}
