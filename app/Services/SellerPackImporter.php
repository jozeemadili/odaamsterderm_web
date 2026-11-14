<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads one seller and its stores from a JSON pack in
 * database/data/sellers/ (sellers that are not in the taxonomy workbook,
 * or whose details came later). Safe to re-run: the seller and stores are
 * matched on their codes and updated in place. Prices and stock stay the
 * seller's to enter in the portal.
 */
class SellerPackImporter
{
    public const DIRECTORY = 'database/data/sellers';

    public function __construct(private readonly SellerStoreImporter $stores) {}

    /** @return list<string> every pack file */
    public static function paths(): array
    {
        $files = glob(base_path(self::DIRECTORY).'/*.json') ?: [];
        sort($files);

        return $files;
    }

    /** @return array{seller: string, stores: int} */
    public function import(string $path): array
    {
        $pack = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($pack) {
            $merchant = $this->importSeller($pack['seller']);
            foreach ($pack['stores'] ?? [] as $row) {
                $this->importStore($merchant, $row);
            }

            return ['seller' => $merchant->business_name, 'stores' => count($pack['stores'] ?? [])];
        });
    }

    /** @param  array<string, mixed>  $row */
    private function importSeller(array $row): Merchant
    {
        $merchant = Merchant::where('code', $row['code'])->first()
            ?? Merchant::whereNull('code')->where('business_name', $row['business_name'])->first()
            ?? new Merchant(['status' => Merchant::STATUS_ACTIVE, 'kyc_status' => Merchant::KYC_PENDING]);

        $merchant->fill(array_filter([
            'code' => $row['code'],
            'business_name' => $row['business_name'],
            'legal_name' => $row['legal_name'] ?? null,
            'owner_name' => $row['owner_name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'website' => $row['website'] ?? null,
            'business_type_id' => isset($row['business_type']) ? BusinessType::firstOrCreate(['name' => $row['business_type']], ['is_active' => true])->id : null,
            'region_id' => isset($row['region']) ? $this->regionId($row['region']) : null,
            'city' => $row['city'] ?? null,
            'physical_address' => $row['physical_address'] ?? null,
            'brand_color' => $row['brand_color'] ?? null,
            'brand_accent_color' => $row['brand_accent_color'] ?? null,
        ], fn ($value) => $value !== null));

        // Approved for the marketplace once, by whoever prepared the pack;
        // an admin's later decision (e.g. suspension) is not overridden.
        if (($row['approved'] ?? false) && $merchant->kyc_status === Merchant::KYC_PENDING) {
            $merchant->kyc_status = Merchant::KYC_APPROVED;
            $merchant->reviewed_at = now();
            $merchant->review_notes = 'Approved from the seller pack (details from the seller\'s own website).';
        }

        $merchant->save();

        // The seller's logo, replaced only when the pack's file changes.
        if (isset($row['logo'])) {
            $file = base_path(self::DIRECTORY.'/'.$row['logo']);
            if (! is_file($file)) {
                throw new RuntimeException("Seller logo {$row['logo']} not found.");
            }
            if ($merchant->logo()?->getCustomProperty('sha1') !== sha1_file($file)) {
                $merchant->addMedia($file)->preservingOriginal()->withCustomProperties(['sha1' => sha1_file($file)])->toMediaCollection('logo');
            }
        }

        return $merchant;
    }

    /** @param  array<string, mixed>  $row */
    private function importStore(Merchant $merchant, array $row): void
    {
        $store = Branch::where('code', $row['code'])->first()
            ?? Branch::whereNull('code')->where('merchant_id', $merchant->id)->where('name', $row['name'])->first()
            ?? new Branch(['merchant_id' => $merchant->id, 'status' => Branch::STATUS_ACTIVE, 'is_marketplace_enabled' => true]);

        if ($store->merchant_id !== $merchant->id) {
            throw new RuntimeException("Store {$row['code']} belongs to another seller.");
        }

        $store->fill([
            'code' => $row['code'],
            'short_code' => $row['short_code'] ?? $store->short_code,
            'name' => $row['name'],
            'store_type' => $row['store_type'] ?? null,
            'region_id' => isset($row['region']) ? $this->regionId($row['region']) : $store->region_id,
            'city' => $row['city'] ?? null,
            'area' => $row['area'] ?? null,
            'address' => $row['address'] ?? null,
            'landmark' => $row['landmark'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'phone' => $row['phone'] ?? null,
            'whatsapp' => $row['whatsapp'] ?? null,
            'email' => $row['email'] ?? null,
            'opening_hours' => isset($row['opening_hours']) ? $this->stores->openingHours($row['opening_hours']) : $store->opening_hours,
            'is_primary' => (bool) ($row['is_hq'] ?? false),
            'pickup_available' => (bool) ($row['pickup_available'] ?? true),
            'pickup_instructions' => $row['pickup_instructions'] ?? null,
            'delivery_radius_km' => $row['delivery_radius_km'] ?? null,
            'delivery_base_fee' => $row['delivery_base_fee'] ?? null,
            'delivery_fee_per_km' => $row['delivery_fee_per_km'] ?? null,
            'cold_chain_capable' => (bool) ($row['cold_chain_capable'] ?? false),
            'vet_advice_on_site' => (bool) ($row['vet_advice_on_site'] ?? false),
            'bio' => $row['bio'] ?? null,
            'bio_sw' => $row['bio_sw'] ?? null,
        ])->save();

        if ($store->is_primary) {
            Branch::where('merchant_id', $merchant->id)->whereKeyNot($store->id)->update(['is_primary' => false]);
        }
    }

    private function regionId(string $code): int
    {
        return Region::where('code', $code)->value('id')
            ?? throw new RuntimeException("Unknown region code '{$code}'.");
    }
}
