<?php

namespace Tests\Feature\Catalog;

use App\Models\InventoryItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use App\Notifications\ProductRequestReviewed;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiResponse;
use App\Services\LabelReader;
use App\Services\ProductRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CentralCatalogueTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();
        $this->staff = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->stores['A']->merchant_id]);
        Sanctum::actingAs($this->staff);
    }

    private function photo(string $name = 'front.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 1600);
    }

    private function request(array $extra = []): ProductRequest
    {
        return app(ProductRequests::class)->create($this->stores['A']->merchant, $this->staff, $extra + [
            'name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma', 'pack_size' => '100 g sachet',
        ], $this->photo(), $this->photo('back.jpg'), ['branch_id' => $this->stores['A']->id, 'unit_price' => 5000, 'quantity' => 12]);
    }

    public function test_agents_pick_products_from_the_catalogue_by_search_and_producer(): void
    {
        $starter = $this->chickenStarter();

        $this->getJson('/api/v1/catalog/pick?q=starter')->assertOk()->assertJsonFragment(['name' => 'Chicken Starter']);
        $this->getJson('/api/v1/catalog/pick?q=nothinglikethis')->assertOk()->assertJsonCount(0, 'data');

        // Seller A already stocks Chicken Starter: its pack size says so.
        $row = collect($this->getJson('/api/v1/catalog/pick?q=chicken starter')->json('data'))->firstWhere('id', $starter->id);
        $this->assertTrue(collect($row['variants'])->contains('stocked', true) || $starter->variants()->doesntExist());

        if ($starter->producer_id) {
            $this->getJson('/api/v1/catalog/pick?producer_ids[]='.$starter->producer_id)->assertOk()->assertJsonFragment(['id' => $starter->id]);
        }
        $this->getJson('/api/v1/catalog/pick/producers')->assertOk();
    }

    public function test_picked_pack_sizes_become_linked_stock_listed_online(): void
    {
        $product = Product::approved()->whereHas('variants')->whereDoesntHave('inventoryItems', fn ($q) => $q->where('branch_id', $this->stores['A']->id))->firstOrFail();
        $variant = $product->variants()->first();

        $this->postJson('/api/v1/inventory/from-catalogue', [
            'branch_id' => $this->stores['A']->id,
            'lines' => [['product_variant_id' => $variant->id, 'unit_price' => 12000, 'quantity' => 8]],
        ])->assertCreated()->assertJson(['added' => 1, 'skipped' => 0]);

        $item = InventoryItem::where('branch_id', $this->stores['A']->id)->where('product_variant_id', $variant->id)->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(8.0, (float) $item->quantity_on_hand);
        $this->assertNotNull($item->category_id);

        // Picking it again skips it.
        $this->postJson('/api/v1/inventory/from-catalogue', [
            'branch_id' => $this->stores['A']->id,
            'lines' => [['product_variant_id' => $variant->id, 'unit_price' => 12000, 'quantity' => 8]],
        ])->assertCreated()->assertJson(['added' => 0, 'skipped' => 1]);

        // Not into another agent's store.
        $this->postJson('/api/v1/inventory/from-catalogue', [
            'branch_id' => $this->stores['B']->id,
            'lines' => [['product_variant_id' => $variant->id, 'unit_price' => 1, 'quantity' => 1]],
        ])->assertNotFound();
    }

    public function test_agents_can_no_longer_create_catalogue_products_by_hand(): void
    {
        $this->postJson('/api/v1/catalog/products', ['name' => 'Anything'])->assertStatus(410)->assertJsonPath('use', '/api/v1/product-requests');
    }

    public function test_a_product_request_needs_both_label_photos_and_can_add_shop_stock(): void
    {
        $this->postJson('/api/v1/product-requests', ['name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma'])
            ->assertUnprocessable()->assertJsonValidationErrors(['front', 'back']);

        $this->post('/api/v1/product-requests', [
            'name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma', 'pack_size' => '100 g sachet',
            'front' => $this->photo(), 'back' => $this->photo('back.jpg'),
            'branch_id' => $this->stores['A']->id, 'unit_price' => 5000, 'quantity' => 12,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('status', 'pending');

        $request = ProductRequest::sole();
        $this->assertNotNull($request->photoUrl(ProductRequest::FRONT));
        $this->assertNull($request->inventoryItem->product_id); // sold in-shop until linked
        $this->assertSame(12.0, (float) $request->inventoryItem->quantity_on_hand);
        $this->getJson('/api/v1/product-requests')->assertOk()->assertJsonPath('data.0.name', 'Kuku Dawa Plus');
    }

    public function test_approving_a_request_builds_the_product_and_links_the_agents_stock(): void
    {
        Notification::fake();
        $request = $this->request();
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);

        $product = app(ProductRequests::class)->approveNew($request, [
            'name' => 'Kuku Dawa Plus', 'producer_name' => 'Shamba Pharma', 'category_code' => 'C2010',
            'animal_codes' => ['AN-CHK'], 'life_stage_codes' => ['LS-POU-STR'], 'form' => 'powder',
            'pack_sizes' => [['name' => '100 g sachet', 'value' => 100, 'unit' => 'g', 'selling_unit' => 'sachet']],
            'active_ingredients' => 'Vitamins A, D3, E', 'dosage' => '1 g per 2 L of drinking water for 5 days',
            'withdrawal_meat_days' => 0, 'use_cases' => ['early_growth'],
        ], $admin);

        $this->assertTrue($product->isApproved());
        $this->assertSame('Shamba Pharma', $product->producer->name);
        $this->assertSame('1 g per 2 L of drinking water for 5 days', $product->inclusion_or_dosage);
        $this->assertNotNull($product->imageUrl()); // the label photo
        $this->assertSame(ProductRequest::APPROVED, $request->fresh()->status);
        $item = $request->inventoryItem->fresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame($product->variants()->first()->id, $item->product_variant_id);
        Notification::assertSentTo($this->staff, ProductRequestReviewed::class);
    }

    public function test_a_request_for_a_product_already_in_the_catalogue_is_linked_to_it(): void
    {
        $request = $this->request();
        $variant = Product::approved()->whereHas('variants')->firstOrFail()->variants()->first();

        app(ProductRequests::class)->linkExisting($request, $variant, User::factory()->create(['user_type' => User::TYPE_INTERNAL]));

        $this->assertSame(ProductRequest::LINKED, $request->fresh()->status);
        $this->assertSame($variant->id, $request->inventoryItem->fresh()->product_variant_id);
        $this->assertSame(0, Product::where('name', 'Kuku Dawa Plus')->count());
    }

    public function test_declining_needs_a_reason_for_the_agent(): void
    {
        $request = $this->request();
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);

        try {
            app(ProductRequests::class)->reject($request, $admin, ' ');
            $this->fail('A reason is required.');
        } catch (ValidationException) {
        }

        app(ProductRequests::class)->reject($request, $admin, 'The label photo is too blurry to read. Please take it again.');
        $this->assertSame(ProductRequest::REJECTED, $request->fresh()->status);
    }

    public function test_the_ai_reads_both_label_photos_and_returns_a_draft(): void
    {
        $request = $this->request();
        $fake = new class implements AiClient
        {
            public array $prompt = [];

            public function structured(string $system, string|array $prompt, array $schema, int $maxTokens = 4000): AiResponse
            {
                $this->prompt = $prompt;

                return new AiResponse(['name' => 'Kuku Dawa Plus', 'category_code' => 'C2010', 'dosage' => '1 g per 2 L'], 'fake', 10, 10);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $draft = (new LabelReader($fake))->draft($request);

        $this->assertSame('1 g per 2 L', $draft['dosage']);
        $images = collect($fake->prompt[0]['content'])->where('type', 'image');
        $this->assertCount(2, $images);
        $this->assertSame('image/jpeg', $images->first()['source']['mediaType']);
        $this->assertTrue(Producer::where('name', 'Shamba Pharma')->doesntExist()); // only made on approval
    }
}
