<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create([
            'name' => 'Odaamsterderm Hub Super Admin',
            'email' => 'admin@odaamsterderm.test',
            'user_type' => User::TYPE_INTERNAL,
        ]);
        $superAdmin->assignRole('super_admin');

        $this->call(AnimalCatalogSeeder::class);

        $merchant = Merchant::create([
            'business_name' => 'Kilimo Bora Agrovet',
            'owner_name' => 'Amina Hassan',
            'phone' => '+255700000001',
            'email' => 'amina@example.com',
            'business_type' => 'Agrovet / animal feeds',
            'region' => 'Dar es Salaam',
            'city' => 'Kinondoni',
            'kyc_status' => Merchant::KYC_APPROVED,
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now(),
        ]);

        $merchantOwner = User::factory()->create([
            'name' => 'Amina Hassan',
            'email' => 'owner@odaamsterderm.test',
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $merchant->id,
            'phone' => $merchant->phone,
        ]);
        $merchantOwner->assignRole('merchant_owner');

        $store = Branch::create([
            'merchant_id' => $merchant->id,
            'name' => 'Kilimo Bora — Mwenge',
            'address' => 'Sam Nujoma Road, Mwenge',
            'city' => 'Kinondoni',
            'phone' => '+255700000001',
            'latitude' => -6.7700,
            'longitude' => 39.2290,
            'pickup_instructions' => 'Collect at the counter next to the feed store entrance.',
            'opening_hours' => collect(Branch::DAYS)->keys()->mapWithKeys(fn ($day) => [
                $day => $day === 'sun' ? null : ['open' => '08:00', 'close' => $day === 'sat' ? '14:00' : '18:00'],
            ])->all(),
            'is_primary' => true,
        ]);

        $stock = [
            // [catalog product name, on hand, reorder level, cost, price, exposed online]
            ['Chicken Starter', 100, 20, 41000, 45000, 30],
            ['Chicken Grower', 60, 15, 39000, 43000, 20],
            ['Layers Mash', 8, 10, 52000, 58000, 5],
            ['Newcastle Disease Vaccine (Lasota)', 40, 10, 6500, 8000, 40],
            ['Dairy Meal', 25, 5, 48000, 54000, 0],
            ['Albendazole Dewormer', 12, 4, 22000, 27000, 6],
        ];

        $inventory = app(InventoryService::class);

        foreach ($stock as [$productName, $onHand, $reorder, $cost, $price, $online]) {
            $product = Product::where('name', $productName)->firstOrFail();

            $item = InventoryItem::create([
                'merchant_id' => $merchant->id,
                'branch_id' => $store->id,
                'product_id' => $product->id,
                'name' => $product->displayName(),
                'unit' => $product->unit,
                'reorder_level' => $reorder,
                'unit_cost' => $cost,
                'unit_price' => $price,
            ]);

            $inventory->stockIn($item, $onHand, ['reference' => 'opening_stock', 'recorded_by' => $merchantOwner->id]);

            if ($online > 0) {
                $inventory->updateMarketplace($item, true, $online, true, ['recorded_by' => $merchantOwner->id]);
            }
        }

        $this->call(MarketplaceDemoSeeder::class);
        $this->call(ProducerDemoSeeder::class);
    }
}
