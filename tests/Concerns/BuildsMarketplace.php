<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\InventoryService;
use Database\Seeders\AnimalCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Three sellers around Dar es Salaam offering Chicken Starter 25KG, as in
 * the requirements example: A near (45,000, 30 bags), B cheaper and further
 * (43,500, 25 bags), C furthest and out of stock (44,000).
 */
trait BuildsMarketplace
{
    /** Sinza — the customer's position in these tests. */
    protected float $customerLat = -6.7790;

    protected float $customerLng = 39.2350;

    /** @var array<string, Branch> */
    protected array $stores = [];

    protected function buildMarketplace(): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, AnimalCatalogSeeder::class]);

        $this->stores['A'] = $this->seller('Seller A', -6.7700, 39.2290, [['Chicken Starter', 100, 30, 45000], ['Layers Mash', 10, 5, 58000]]);
        $this->stores['B'] = $this->seller('Seller B', -6.7445, 39.2150, [['Chicken Starter', 60, 25, 43500]]);
        $this->stores['C'] = $this->seller('Seller C', -6.7290, 39.1880, [['Chicken Starter', 0, 0, 44000], ['Tilapia Floating Feed', 50, 20, 61000]]);
    }

    /** @param  array<int, array{0:string,1:int,2:int,3:int}>  $stock  product, on hand, online, price */
    protected function seller(string $name, float $lat, float $lng, array $stock, bool $approved = true): Branch
    {
        $merchant = Merchant::create([
            'business_name' => $name,
            'owner_name' => $name.' Owner',
            'phone' => '+2557'.random_int(10000000, 99999999),
            'kyc_status' => $approved ? Merchant::KYC_APPROVED : Merchant::KYC_PENDING,
        ]);

        $store = Branch::create(['merchant_id' => $merchant->id, 'name' => $name.' Store', 'latitude' => $lat, 'longitude' => $lng]);

        foreach ($stock as [$productName, $onHand, $online, $price]) {
            $product = Product::where('name', $productName)->firstOrFail();
            $item = InventoryItem::create([
                'merchant_id' => $merchant->id,
                'branch_id' => $store->id,
                'product_id' => $product->id,
                'name' => $product->displayName(),
                'unit' => $product->unit,
                'unit_price' => $price,
                'unit_cost' => round($price * 0.9),
            ]);

            if ($onHand > 0) {
                app(InventoryService::class)->stockIn($item, $onHand);
            }

            if ($approved) {
                app(InventoryService::class)->updateMarketplace($item, true, $online);
            } else {
                $item->update(['is_marketplace_visible' => true, 'marketplace_quantity' => $online, 'marketplace_status' => 'active']);
            }
        }

        return $store;
    }

    protected function chickenStarter(): Product
    {
        return Product::where('name', 'Chicken Starter')->firstOrFail();
    }
}
