<?php

// Live check of multi-store access, transfers and front/back photos.
// Creates a throwaway seller "Smoke Test Agrovet"; app:fresh-start removes it.
// Run: php artisan tinker --execute="require 'deploy/live-smoke-test.php';"

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use App\Support\StoreAccess;
use Illuminate\Http\UploadedFile;

$ok = fn (bool $pass, string $what) => print ($pass ? 'PASS ' : 'FAIL ').$what.PHP_EOL;

$merchant = Merchant::create(['business_name' => 'Smoke Test Agrovet', 'owner_name' => 'Smoke Test', 'phone' => '+255700999999', 'kyc_status' => Merchant::KYC_APPROVED, 'status' => Merchant::STATUS_ACTIVE]);
$main = Branch::create(['merchant_id' => $merchant->id, 'name' => 'Smoke Main', 'is_primary' => true, 'latitude' => -6.77, 'longitude' => 39.24]);
$second = Branch::create(['merchant_id' => $merchant->id, 'name' => 'Smoke Annex', 'latitude' => -6.74, 'longitude' => 39.21]);
$owner = User::create(['name' => 'Smoke Owner', 'email' => 'smoke-owner@odaamsterderm.invalid', 'password' => bin2hex(random_bytes(16)), 'user_type' => User::TYPE_MERCHANT, 'merchant_id' => $merchant->id, 'all_branches' => true]);
$owner->assignRole('merchant_owner');
$annexStaff = User::create(['name' => 'Smoke Annex Staff', 'email' => 'smoke-annex@odaamsterderm.invalid', 'password' => bin2hex(random_bytes(16)), 'user_type' => User::TYPE_MERCHANT, 'merchant_id' => $merchant->id, 'all_branches' => false]);
$annexStaff->assignRole('merchant_sales');
$annexStaff->branches()->sync([$second->id]);

$product = Product::approved()->first();
$item = InventoryItem::create([
    'merchant_id' => $merchant->id, 'branch_id' => $main->id, 'product_id' => $product?->id,
    'category_id' => InventoryCategory::firstOrCreate(['merchant_id' => $merchant->id, 'name' => 'Feeds'])->id,
    'name' => 'Smoke Layers Mash', 'unit' => 'bag', 'reorder_level' => 1, 'unit_cost' => 40000, 'unit_price' => 52000,
]);
app(InventoryService::class)->stockIn($item, 20);

$item->addMedia(UploadedFile::fake()->image('front.jpg', 400, 400))->toMediaCollection('image');
$item->addMedia(UploadedFile::fake()->image('back.jpg', 400, 400))->toMediaCollection(InventoryItem::BACK_PHOTO);
$item->refresh();
$ok(count($item->photoUrls()) === 2, 'front and back photos stored');
$ok(is_file($item->backImage()->getPath()), 'back photo file is on disk');

$ok(StoreAccess::branchIds($annexStaff) === [$second->id], 'annex staff see only Smoke Annex');
$ok(InventoryItem::forUser($annexStaff)->count() === 0, 'annex staff cannot see main store stock');

$service = app(StockTransferService::class);
$transfer = $service->send($owner, $main->id, $second->id, [['inventory_item_id' => $item->id, 'quantity' => 5]], 'Smoke test');
$ok((float) $item->fresh()->quantity_on_hand === 15.0, 'stock left the main store on send ('.$transfer->number.')');

$service->receive($transfer, $annexStaff);
$arrived = InventoryItem::where('branch_id', $second->id)->first();
$ok($arrived && (float) $arrived->quantity_on_hand === 5.0, 'stock arrived in the annex on receive');
$ok($arrived && count($arrived->photoUrls()) === 2, 'photos copied to the annex product');
$ok(InventoryItem::forUser($annexStaff)->count() === 1, 'annex staff now see the received product');

$item->update(['is_marketplace_visible' => true, 'marketplace_quantity' => 10]);

echo 'TOKEN '.$owner->createToken('smoke')->plainTextToken.PHP_EOL;
echo 'ITEM '.$item->id.' TRANSFER '.$transfer->id.' PRODUCT '.$product?->slug.' STORE '.$main->id.PHP_EOL;
