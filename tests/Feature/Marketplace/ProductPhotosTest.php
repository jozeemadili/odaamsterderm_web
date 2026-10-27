<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Portal\Inventory\Show as InventoryShow;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class ProductPhotosTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    public function test_seller_adds_front_and_back_photos_on_web_and_app(): void
    {
        Storage::fake('public');
        $this->createSeller();
        $item = $this->createItem(['name' => 'Layers Mash']);

        Livewire::actingAs($this->owner)->test(InventoryShow::class, ['item' => $item])
            ->set('photo', UploadedFile::fake()->image('front.jpg'))
            ->set('backPhoto', UploadedFile::fake()->image('back.jpg'))
            ->assertHasNoErrors()
            ->assertSee('Front')->assertSee('Back');

        $item->refresh();
        $this->assertNotNull($item->image());
        $this->assertNotNull($item->backImage());
        $this->assertCount(2, $item->photoUrls());

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v1/inventory/{$item->id}/image?side=back")->assertOk()->assertJsonPath('data.back_image_url', null);
        $this->assertNotNull($item->fresh()->image());

        $this->post("/api/v1/inventory/{$item->id}/image", ['image' => UploadedFile::fake()->image('b.jpg'), 'side' => 'back'], ['Accept' => 'application/json'])
            ->assertOk();
        $this->assertNotNull(InventoryItem::find($item->id)->backImage());
        $this->assertNotNull($this->getJson("/api/v1/inventory/{$item->id}")->json('data.back_image_url'));
    }
}
