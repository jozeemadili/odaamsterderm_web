<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

/**
 * Demo producers: the poultry feeds are linked to Silverlands, and a
 * second "Chicken Grower 25KG" from a made-up producer is stocked at
 * Tegeta, so customers can compare the same product from two factories.
 * Safe to run again. Real logos are uploaded by admins.
 */
class ProducerDemoSeeder extends Seeder
{
    public const DEMO_PRODUCER = 'Bora Feeds (demo)';

    public function run(): void
    {
        $silverlands = Producer::firstOrCreate(['name' => 'Silverlands'], ['slug' => 'silverlands', 'country' => 'Tanzania']);
        $demo = Producer::firstOrCreate(['name' => self::DEMO_PRODUCER], [
            'slug' => 'bora-feeds-demo',
            'country' => 'Tanzania',
            'description' => 'Example producer for testing — not a real company.',
        ]);

        if (! $demo->logoUrl() && function_exists('imagecreatetruecolor')) {
            $demo->addMedia($this->demoLogo())->usingFileName('bora-feeds-demo.png')->toMediaCollection('logo');
        }

        Product::whereNull('producer_id')
            ->whereIn('name', ['Chicken Starter', 'Chicken Grower', 'Chicken Finisher', 'Layers Mash'])
            ->get()
            ->each(fn (Product $p) => $p->update(['producer_id' => $silverlands->id]));

        $grower = Product::where('name', 'Chicken Grower')->where('producer_id', $silverlands->id)->first();
        if (! $grower) {
            return;
        }

        $boraGrower = Product::firstOrCreate(
            ['name' => 'Chicken Grower', 'pack_size' => '25KG', 'producer_id' => $demo->id],
            [
                'slug' => Product::uniqueSlug('Chicken Grower 25KG '.$demo->name),
                'animal_type_id' => $grower->animal_type_id,
                'product_category_id' => $grower->product_category_id,
                'unit' => 'bag',
                'status' => Product::STATUS_APPROVED,
                'reviewed_at' => now(),
            ],
        );

        $store = Branch::whereHas('merchant', fn ($q) => $q->where('email', 'tegeta@odaamsterderm.test'))->first();
        if (! $store || InventoryItem::where('branch_id', $store->id)->where('product_id', $boraGrower->id)->exists()) {
            return;
        }

        $item = InventoryItem::create([
            'merchant_id' => $store->merchant_id,
            'branch_id' => $store->id,
            'product_id' => $boraGrower->id,
            'name' => $boraGrower->displayName(),
            'unit' => 'bag',
            'reorder_level' => 5,
            'unit_cost' => 35000,
            'unit_price' => 39500,
        ]);
        $owner = User::where('email', 'tegeta@odaamsterderm.test')->value('id');
        $inventory = app(InventoryService::class);
        $inventory->stockIn($item, 30, ['reference' => 'opening_stock', 'recorded_by' => $owner]);
        $inventory->updateMarketplace($item, true, 20, true, ['recorded_by' => $owner]);
    }

    /** A plain "BF" square, so the demo shows how a logo looks. */
    protected function demoLogo(): string
    {
        $img = imagecreatetruecolor(128, 128);
        imagefill($img, 0, 0, imagecolorallocate($img, 22, 101, 52));
        imagefilledellipse($img, 64, 64, 104, 104, imagecolorallocate($img, 201, 151, 46));
        $white = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 55, 56, 'BF', $white);
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
