<?php

namespace Tests\Feature\Market;

use App\Livewire\Admin\Products\Index as AdminProducts;
use App\Livewire\Portal\Inventory\Show as StockShow;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ProductPhotoTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private InventoryItem $offerB;

    private User $sellerB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->buildMarketplace();

        $this->offerB = InventoryItem::where('branch_id', $this->stores['B']->id)->firstOrFail(); // Chicken Starter
        $this->sellerB = User::factory()->create(['user_type' => User::TYPE_MERCHANT, 'merchant_id' => $this->offerB->merchant_id]);
        $this->sellerB->assignRole('merchant_owner');
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('starter-bag.jpg', 600, 600);
    }

    public function test_seller_adds_changes_and_removes_a_photo_on_the_web(): void
    {
        Livewire::actingAs($this->sellerB)->test(StockShow::class, ['item' => $this->offerB])
            ->set('photo', $this->photo())
            ->assertHasNoErrors()
            ->assertSee('Front photo saved');
        $this->assertNotNull($this->offerB->fresh()->image());

        Livewire::actingAs($this->sellerB)->test(StockShow::class, ['item' => $this->offerB])
            ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->assertHasErrors('photo')
            ->call('removePhoto')
            ->assertSee('No front photo');
        $this->assertNull($this->offerB->fresh()->image());
    }

    public function test_customers_see_a_seller_photo_when_the_catalog_has_no_image(): void
    {
        $this->offerB->addMedia($this->photo())->toMediaCollection('image');
        $url = $this->offerB->fresh()->image()->getUrl();

        $this->get('/')->assertOk()->assertSee($url, false);
        $this->get('/products/chicken-starter-25kg')->assertOk()->assertSee($url, false);
        $this->getJson('/api/v1/market/products?search=starter')->assertJsonPath('data.0.image_url', $url);

        $offers = collect($this->getJson('/api/v1/market/products/'.$this->offerB->product_id)->json('offers'));
        $this->assertSame($url, $offers->firstWhere('id', $this->offerB->id)['image_url']);

        // A catalog image, when set, is the product's main picture; the seller's photo stays on its offer.
        $product = Product::find($this->offerB->product_id);
        $product->addMedia($this->photo())->toMediaCollection('image');
        $catalogUrl = $product->fresh()->image()->getUrl();
        $this->getJson('/api/v1/market/products?search=starter')->assertJsonPath('data.0.image_url', $catalogUrl);
        $offers = collect($this->getJson('/api/v1/market/products/'.$this->offerB->product_id)->json('offers'));
        $this->assertSame($url, $offers->firstWhere('id', $this->offerB->id)['image_url']);
    }

    public function test_app_can_upload_and_remove_a_stock_photo(): void
    {
        Sanctum::actingAs($this->sellerB);

        $this->post("/api/v1/inventory/{$this->offerB->id}/image", ['image' => $this->photo()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.image_url', fn ($u) => is_string($u) && $u !== '');

        $this->deleteJson("/api/v1/inventory/{$this->offerB->id}/image")->assertOk()->assertJsonPath('data.image_url', null);
        $this->assertNull($this->offerB->fresh()->image());

        // Another seller's stock is off limits.
        $other = InventoryItem::where('branch_id', $this->stores['A']->id)->firstOrFail();
        $this->deleteJson("/api/v1/inventory/{$other->id}/image")->assertForbidden();
    }

    public function test_admin_removes_a_catalog_image(): void
    {
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $admin->assignRole('super_admin');
        $product = Product::find($this->offerB->product_id);
        $product->addMedia($this->photo())->toMediaCollection('image');

        Livewire::actingAs($admin)->test(AdminProducts::class)
            ->call('startEditing', $product->id)
            ->assertSee('Front photo')
            ->call('removeImage', $product->id);

        $this->assertNull($product->fresh()->image());
    }
}
