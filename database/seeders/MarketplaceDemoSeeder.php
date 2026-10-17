<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

/**
 * Extra sellers around Dar es Salaam so the marketplace has something to
 * compare — mirrors the "Chicken Starter 25KG" example in the requirements
 * (one cheaper seller further away, one out of stock). Demo data only.
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $inventory = app(InventoryService::class);

        $sellers = [
            [
                'business' => 'Tegeta Feeds & Vet', 'owner' => 'Baraka Mushi', 'email' => 'tegeta@odaamsterderm.test',
                'store' => 'Tegeta Feeds — Main', 'address' => 'Bagamoyo Road, Tegeta', 'city' => 'Kinondoni',
                'lat' => -6.7445, 'lng' => 39.2150,
                'stock' => [
                    // product, on hand, exposed online, price
                    ['Chicken Starter', 60, 25, 43500],
                    ['Chicken Grower', 40, 40, 42000],
                    ['Gumboro Vaccine', 15, 10, 25000],
                    ['Dairy Meal', 30, 12, 53000],
                ],
            ],
            [
                'business' => 'Mbezi Livestock Supplies', 'owner' => 'Neema Kisanga', 'email' => 'mbezi@odaamsterderm.test',
                'store' => 'Mbezi Livestock — Mbezi Beach', 'address' => 'Mbezi Beach Road', 'city' => 'Kinondoni',
                'lat' => -6.7290, 'lng' => 39.1880,
                'stock' => [
                    ['Chicken Starter', 0, 0, 44000],
                    ['Tilapia Floating Feed', 50, 20, 61000],
                    ['Pig Grower Pellets', 20, 10, 72000],
                    ['Poultry Multivitamin', 100, 60, 3500],
                ],
            ],
        ];

        foreach ($sellers as $s) {
            $merchant = Merchant::create([
                'business_name' => $s['business'],
                'owner_name' => $s['owner'],
                'phone' => '+2557'.random_int(10000000, 99999999),
                'email' => $s['email'],
                'business_type' => 'Agrovet / animal feeds',
                'region' => 'Dar es Salaam',
                'city' => $s['city'],
                'kyc_status' => Merchant::KYC_APPROVED,
                'reviewed_at' => now(),
            ]);

            $owner = User::factory()->create([
                'name' => $s['owner'],
                'email' => $s['email'],
                'user_type' => User::TYPE_MERCHANT,
                'merchant_id' => $merchant->id,
            ]);
            $owner->assignRole('merchant_owner');

            $store = Branch::create([
                'merchant_id' => $merchant->id,
                'name' => $s['store'],
                'address' => $s['address'],
                'city' => $s['city'],
                'latitude' => $s['lat'],
                'longitude' => $s['lng'],
                'pickup_instructions' => 'Show your order reference at the counter.',
                'opening_hours' => collect(Branch::DAYS)->keys()->mapWithKeys(fn ($day) => [
                    $day => $day === 'sun' ? null : ['open' => '07:30', 'close' => '18:30'],
                ])->all(),
                'is_primary' => true,
            ]);

            foreach ($s['stock'] as [$productName, $onHand, $online, $price]) {
                $product = Product::where('name', $productName)->firstOrFail();

                $item = InventoryItem::create([
                    'merchant_id' => $merchant->id,
                    'branch_id' => $store->id,
                    'product_id' => $product->id,
                    'name' => $product->displayName(),
                    'unit' => $product->unit,
                    'reorder_level' => 5,
                    'unit_cost' => round($price * 0.88),
                    'unit_price' => $price,
                ]);

                if ($onHand > 0) {
                    $inventory->stockIn($item, $onHand, ['reference' => 'opening_stock', 'recorded_by' => $owner->id]);
                }

                // Listed even at zero so customers see "Out of stock" when comparing.
                $inventory->updateMarketplace($item, true, $online, true, ['recorded_by' => $owner->id]);
            }
        }

        $customer = User::factory()->create([
            'name' => 'Juma Mwinyi',
            'email' => 'customer@odaamsterderm.test',
            'user_type' => User::TYPE_CUSTOMER,
            'phone' => '+255700000099',
            'latitude' => -6.7790,
            'longitude' => 39.2350,
            'location_label' => 'Sinza, Dar es Salaam',
        ]);
        $customer->assignRole('customer');
    }
}
