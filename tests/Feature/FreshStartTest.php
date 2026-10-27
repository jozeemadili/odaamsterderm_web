<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class FreshStartTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    public function test_it_clears_business_data_and_keeps_admins_and_the_catalog(): void
    {
        Storage::fake('public');
        $this->createSeller();
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $admin->assignRole('super_admin');
        $item = $this->createItem();
        $item->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('image');
        $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);

        $catalog = Product::where('status', Product::STATUS_APPROVED)->firstOrFail();
        $catalog->addMedia(UploadedFile::fake()->image('cat.jpg'))->toMediaCollection('image');
        $proposal = Product::create([...$catalog->only(['animal_type_id', 'product_category_id', 'unit']), 'name' => 'Seller idea', 'slug' => 'seller-idea', 'status' => Product::STATUS_PENDING, 'proposed_by_merchant_id' => $this->merchant->id]);

        $this->artisan('app:fresh-start --dry-run')->assertSuccessful();
        $this->assertSame(1, Merchant::count());

        $this->artisan('app:fresh-start')->expectsQuestion('This cannot be undone. Type CLEAR to delete the data above', 'no')->assertFailed();
        $this->assertSame(1, Merchant::count());

        $this->artisan('app:fresh-start --force')->assertSuccessful();

        $this->assertSame(0, Merchant::count());
        $this->assertSame(0, InventoryItem::count());
        $this->assertNull(User::find($customer->id));
        $this->assertNull(User::find($this->owner->id));
        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
        $this->assertNotNull($catalog->fresh()->image());
        $this->assertNull(Product::find($proposal->id));
        $this->assertSame(1, Media::count());
    }
}
