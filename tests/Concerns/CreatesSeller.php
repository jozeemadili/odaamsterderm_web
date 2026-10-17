<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AnimalCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

/** An approved seller with a located store and one catalog-linked item. */
trait CreatesSeller
{
    protected Merchant $merchant;

    protected User $owner;

    protected Branch $store;

    protected function createSeller(bool $approved = true): void
    {
        $this->seed([RolesAndPermissionsSeeder::class, AnimalCatalogSeeder::class]);

        $this->merchant = Merchant::create([
            'business_name' => 'Kilimo Bora Agrovet',
            'owner_name' => 'Amina Hassan',
            'phone' => '+255700000001',
            'kyc_status' => $approved ? Merchant::KYC_APPROVED : Merchant::KYC_PENDING,
        ]);

        $this->owner = User::factory()->create([
            'user_type' => User::TYPE_MERCHANT,
            'merchant_id' => $this->merchant->id,
        ]);
        $this->owner->assignRole('merchant_owner');

        $this->store = Branch::create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Mwenge',
            'latitude' => -6.77,
            'longitude' => 39.229,
            'is_primary' => true,
        ]);
    }

    protected function createItem(array $overrides = []): InventoryItem
    {
        $product = Product::where('name', 'Chicken Starter')->first();

        return InventoryItem::create($overrides + [
            'merchant_id' => $this->merchant->id,
            'branch_id' => $this->store->id,
            'category_id' => InventoryCategory::firstOrCreate(['merchant_id' => $this->merchant->id, 'name' => 'Feeds'])->id,
            'product_id' => $product->id,
            'name' => $product->displayName(),
            'unit' => 'bag',
            'reorder_level' => 10,
            'unit_cost' => 41000,
            'unit_price' => 45000,
        ]);
    }
}
