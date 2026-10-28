<?php

namespace Tests\Feature\Marketplace;

use App\Livewire\Admin\Merchants\Show as AdminMerchantShow;
use App\Livewire\Portal\Stores\Show as StoreShow;
use App\Models\Branch;
use App\Models\Merchant;
use App\Models\Region;
use App\Models\User;
use App\Services\SellerStoreImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesSeller;
use Tests\TestCase;

class SellerStoreImportTest extends TestCase
{
    use CreatesSeller;
    use RefreshDatabase;

    public function test_command_imports_sellers_regions_and_stores(): void
    {
        $this->artisan('marketplace:import-sellers')->assertSuccessful();

        $this->assertSame(12, Merchant::whereNotNull('code')->count());
        $this->assertSame(6, Branch::whereNotNull('code')->count());

        $amsterdam = Merchant::where('code', 'SEL-AMS')->sole();
        $this->assertSame('Amsterdam Group', $amsterdam->business_name);
        $this->assertSame('Agrovet', $amsterdam->businessTypeRef->name);
        $this->assertTrue($amsterdam->canSellOnMarketplace());
        $this->assertSame(6, $amsterdam->branches()->count());

        // Hatcheries wait for approval and their Selcom merchant id.
        $kibo = Merchant::where('code', 'SEL-KIBO')->sole();
        $this->assertSame('Hatchery', $kibo->businessTypeRef->name);
        $this->assertSame(Merchant::KYC_PENDING, $kibo->kyc_status);

        $tegeta = Branch::where('code', 'ST-AMS-TEG')->sole();
        $this->assertTrue($tegeta->is_primary);
        $this->assertSame('TEG', $tegeta->short_code);
        $this->assertSame('DAR ES SALAAM', $tegeta->region->name);
        $this->assertSame(['open' => '07:30', 'close' => '18:30'], $tegeta->opening_hours['sat']);
        $this->assertSame(['open' => '09:00', 'close' => '14:00'], $tegeta->opening_hours['sun']);
        $this->assertSame(30.0, $tegeta->delivery_radius_km);
        $this->assertSame(5000.0, $tegeta->delivery_base_fee);
        $this->assertTrue($tegeta->cold_chain_capable);
        $this->assertStringStartsWith('Ofisi kuu', $tegeta->bio_sw);

        // 5,000 + 500 × 12.3 km = 11,150 → rounded up to 11,200; beyond 30 km: none.
        $this->assertSame(11200.0, $tegeta->deliveryFeeFor(12.3));
        $this->assertNull($tegeta->deliveryFeeFor(31));

        $posta = Branch::where('code', 'ST-AMS-PST')->sole();
        $this->assertFalse($posta->vet_advice_on_site);
        $this->assertNull($posta->opening_hours['sun']);

        $this->assertSame('Lake Zone', Region::where('name', 'Mwanza')->value('zone'));
    }

    public function test_live_seller_and_main_store_take_over_their_workbook_codes(): void
    {
        $live = Merchant::create(['business_name' => 'Amsterderm Group', 'owner_name' => 'Owner', 'phone' => '+255700000000', 'kyc_status' => Merchant::KYC_APPROVED]);
        $main = Branch::create(['merchant_id' => $live->id, 'name' => 'Amsterdem Group - Main']);
        $ubungo = Branch::create(['merchant_id' => $live->id, 'name' => 'Amsterdem Group - Ubungo', 'is_primary' => true]);

        $counts = app(SellerStoreImporter::class)->import();

        $this->assertSame(2, $counts['live_records_renamed']);
        $this->assertSame('SEL-AMS', $live->fresh()->code);
        $this->assertSame('Amsterdam Group', $live->fresh()->business_name);
        $this->assertSame('ST-AMS-TEG', $main->fresh()->code);
        $this->assertSame('Amsterdam Group - Tegeta (HQ & Warehouse)', $main->fresh()->name);
        $this->assertSame(7, $live->branches()->count());

        // Ubungo is left for a decision, but Tegeta is now the head office.
        $this->assertNull($ubungo->fresh()->code);
        $this->assertFalse($ubungo->fresh()->is_primary);

        // Re-running changes nothing.
        $again = app(SellerStoreImporter::class)->import();
        $this->assertSame(0, $again['live_records_renamed']);
        $this->assertSame(12, Merchant::count());
        $this->assertSame(7, Branch::count());
    }

    public function test_opening_hours_text_is_read_or_left_alone(): void
    {
        $importer = app(SellerStoreImporter::class);

        $hours = $importer->openingHours('Mon-Fri 08:00-17:30; Sat 8:30-14:00; Sun closed');
        $this->assertSame(['open' => '08:00', 'close' => '17:30'], $hours['wed']);
        $this->assertSame(['open' => '08:30', 'close' => '14:00'], $hours['sat']);
        $this->assertNull($hours['sun']);

        $this->assertNull($importer->openingHours('Ask at the counter'));
    }

    public function test_seller_sets_delivery_and_services_and_buyers_see_them(): void
    {
        $this->createSeller();
        $store = Branch::create(['merchant_id' => $this->merchant->id, 'name' => 'Tegeta', 'latitude' => -6.65, 'longitude' => 39.2]);

        Livewire::actingAs($this->owner)->test(StoreShow::class, ['store' => $store])
            ->call('startEditing')
            ->set('delivery_radius_km', '20')
            ->set('delivery_base_fee', '4000')
            ->set('delivery_fee_per_km', '600')
            ->set('cold_chain_capable', true)
            ->set('bio', 'Feeds and vaccines in Tegeta.')
            ->set('bio_sw', 'Vyakula na chanjo Tegeta.')
            ->call('save')
            ->assertHasNoErrors();

        $store->refresh();
        $this->assertTrue($store->offersDelivery());
        $this->assertTrue($store->cold_chain_capable);

        $this->get(route('market.store', $store))
            ->assertOk()
            ->assertSee('Delivers within 20 km')
            ->assertSee('Cold storage for vaccines')
            ->assertSee('Feeds and vaccines in Tegeta.');

        $this->getJson("/api/v1/market/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('store.cold_chain_capable', true)
            ->assertJsonPath('store.delivery.radius_km', 20);
    }

    public function test_admin_records_the_selcom_merchant_id_of_a_hatchery(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(SellerStoreImporter::class)->import();
        $admin = User::factory()->create(['user_type' => User::TYPE_INTERNAL]);
        $admin->assignRole('super_admin');
        $kibo = Merchant::where('code', 'SEL-KIBO')->sole();

        $this->actingAs($admin)->get(route('admin.merchants.show', $kibo))->assertOk()->assertSee('SEL-KIBO');

        Livewire::actingAs($admin)->test(AdminMerchantShow::class, ['merchant' => $kibo])
            ->call('startEditing')
            ->set('phone', '+255700000201')
            ->set('selcom_merchant_id', 'SLC-KIBO-001')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertSame('SLC-KIBO-001', $kibo->fresh()->selcom_merchant_id);
    }
}
