<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\User;
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
            'name' => 'DukaFlow Super Admin',
            'email' => 'admin@dukaflow.test',
            'user_type' => User::TYPE_INTERNAL,
        ]);
        $superAdmin->assignRole('super_admin');

        $merchant = Merchant::create([
            'business_name' => 'Mama Amina Duka',
            'owner_name' => 'Amina Hassan',
            'phone' => '+255700000001',
            'email' => 'amina@example.com',
            'business_type' => 'Duka / mini-supermarket',
            'region' => 'Dar es Salaam',
            'city' => 'Kinondoni',
            'kyc_status' => Merchant::KYC_APPROVED,
            'reviewed_by' => $superAdmin->id,
            'reviewed_at' => now(),
        ]);

        $merchantOwner = User::factory()->create([
            'name' => 'Amina Hassan',
            'email' => 'owner@dukaflow.test',
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $merchant->id,
            'phone' => $merchant->phone,
        ]);
        $merchantOwner->assignRole('merchant_owner');

        $products = [
            ['name' => 'Rice 2kg', 'sku' => 'RIC-2KG', 'unit' => 'bag', 'quantity_on_hand' => 40, 'reorder_level' => 10, 'unit_cost' => 4500, 'unit_price' => 5500],
            ['name' => 'Cooking Oil 1L', 'sku' => 'OIL-1L', 'unit' => 'bottle', 'quantity_on_hand' => 25, 'reorder_level' => 8, 'unit_cost' => 5200, 'unit_price' => 6500],
            ['name' => 'Sugar 1kg', 'sku' => 'SUG-1KG', 'unit' => 'bag', 'quantity_on_hand' => 30, 'reorder_level' => 10, 'unit_cost' => 2600, 'unit_price' => 3200],
            ['name' => 'Maize Flour 2kg', 'sku' => 'FLR-2KG', 'unit' => 'bag', 'quantity_on_hand' => 35, 'reorder_level' => 10, 'unit_cost' => 3000, 'unit_price' => 3800],
            ['name' => 'Soap Bar', 'sku' => 'SOP-BAR', 'unit' => 'pcs', 'quantity_on_hand' => 60, 'reorder_level' => 15, 'unit_cost' => 800, 'unit_price' => 1200],
            ['name' => 'Bottled Water 500ml', 'sku' => 'WTR-500', 'unit' => 'pcs', 'quantity_on_hand' => 100, 'reorder_level' => 20, 'unit_cost' => 300, 'unit_price' => 500],
            ['name' => 'Soda 500ml', 'sku' => 'SOD-500', 'unit' => 'pcs', 'quantity_on_hand' => 5, 'reorder_level' => 12, 'unit_cost' => 700, 'unit_price' => 1000],
            ['name' => 'Bread Loaf', 'sku' => 'BRD-LF', 'unit' => 'pcs', 'quantity_on_hand' => 15, 'reorder_level' => 5, 'unit_cost' => 1800, 'unit_price' => 2500],
        ];

        foreach ($products as $product) {
            InventoryItem::create($product + ['merchant_id' => $merchant->id]);
        }
    }
}
