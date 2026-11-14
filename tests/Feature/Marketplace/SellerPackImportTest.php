<?php

namespace Tests\Feature\Marketplace;

use App\Models\Branch;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerPackImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_harsho_pack_completes_the_workbook_seller_with_its_stores(): void
    {
        Storage::fake('public');
        $this->artisan('marketplace:import-sellers')->assertSuccessful();
        $this->assertSame(Merchant::KYC_PENDING, Merchant::where('code', 'SEL-HARS')->value('kyc_status'));

        $this->artisan('marketplace:import-seller-packs')->assertSuccessful();
        $this->artisan('marketplace:import-seller-packs')->assertSuccessful(); // safe to re-run

        $harsho = Merchant::where('code', 'SEL-HARS')->sole();
        $this->assertSame('Harsho Group of Companies', $harsho->legal_name);
        $this->assertSame('https://www.harshotz.com', $harsho->website);
        $this->assertTrue($harsho->canSellOnMarketplace());
        $this->assertCount(1, $harsho->getMedia('logo'));
        $this->assertSame(1, Merchant::where('business_name', 'Harsho')->count());

        $stores = Branch::where('merchant_id', $harsho->id)->orderBy('code')->get();
        $this->assertSame(['ST-HARS-DOD', 'ST-HARS-MSH'], $stores->pluck('code')->all());
        $moshi = $stores->firstWhere('code', 'ST-HARS-MSH');
        $this->assertTrue($moshi->is_primary);
        $this->assertTrue($moshi->is_marketplace_enabled);
        $this->assertSame('Kilimanjaro', $moshi->region->name);
        $this->assertSame(['open' => '08:00', 'close' => '18:00'], $moshi->opening_hours['fri']);
        $this->assertNull($moshi->opening_hours['sun']);
    }

    public function test_an_admin_suspension_is_not_undone_by_a_re_import(): void
    {
        Storage::fake('public');
        $this->artisan('marketplace:import-sellers')->assertSuccessful();
        $this->artisan('marketplace:import-seller-packs')->assertSuccessful();

        Merchant::where('code', 'SEL-HARS')->update(['kyc_status' => Merchant::KYC_REJECTED]);
        $this->artisan('marketplace:import-seller-packs')->assertSuccessful();

        $this->assertSame(Merchant::KYC_REJECTED, Merchant::where('code', 'SEL-HARS')->value('kyc_status'));
    }
}
