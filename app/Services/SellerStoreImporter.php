<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BusinessType;
use App\Models\Merchant;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Loads sellers, regions and stores from the Oda/Amsterdam taxonomy
 * workbook (Sellers, Regions, Stores tabs).
 *
 * Rows are matched on their workbook code (SEL-AMS, ST-AMS-TEG), so
 * re-running updates in place. Live records listed in Map_Stores_Sellers
 * ("Amsterderm Group", "Amsterdem Group - Main") take over their workbook
 * code and new name, keeping their id, stock and sales history. Regions
 * already exist (the Tanzania list) and are matched on name; only their
 * zone and centre point are filled in. A seller's approval (KYC) is never
 * changed on an existing record: new sellers marked active in the
 * workbook start approved, the rest (hatcheries still to migrate from Oda)
 * start pending.
 */
class SellerStoreImporter
{
    private const SHEETS = ['Sellers', 'Regions', 'Stores', 'Map_Stores_Sellers'];

    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Live sellers and stores that took a new name in this run. */
    private int $renamed = 0;

    /**
     * @return array<string, int|list<string>> rows imported per table, plus live records renamed and regions not found
     */
    public function import(?string $path = null): array
    {
        $sheets = $this->read($path ?? CatalogTaxonomyImporter::defaultPath());
        $this->renamed = 0;

        return DB::transaction(function () use ($sheets) {
            [$liveSellers, $liveStores] = $this->liveNames($sheets['Map_Stores_Sellers']);
            [$regions, $missingRegions] = $this->importRegions($sheets['Regions']);

            $counts = [
                'regions' => count($regions),
                'merchants' => $this->importSellers($sheets['Sellers'], $liveSellers),
                'branches' => $this->importStores($sheets['Stores'], $regions, $liveStores),
                'live_records_renamed' => $this->renamed,
            ];

            if ($missingRegions) {
                $counts['regions_not_found'] = $missingRegions;
            }

            return $counts;
        });
    }

    /**
     * Each sheet as a list of rows keyed by the header row; blank rows dropped.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Taxonomy workbook not found: {$path}");
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(self::SHEETS);
        $workbook = $reader->load($path);

        $sheets = [];

        foreach (self::SHEETS as $name) {
            $sheet = $workbook->getSheetByName($name)
                ?? throw new RuntimeException("Taxonomy workbook has no '{$name}' sheet.");

            $rows = $sheet->toArray(null, false, false, false);
            $header = array_map(fn ($h) => trim((string) $h), array_shift($rows) ?? []);

            $sheets[$name] = array_values(array_filter(
                array_map(fn (array $row) => array_combine($header, array_map($this->clean(...), $row)), $rows),
                fn (array $row) => ($row[$header[0]] ?? null) !== null,
            ));
        }

        return $sheets;
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * Live names that take over a workbook code: [code => live name] for
     * sellers and for stores.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function liveNames(array $rows): array
    {
        $sellers = $stores = [];

        foreach ($rows as $row) {
            $code = (string) $row['new_id'];

            if ($row['type'] === 'seller' && str_starts_with($code, 'SEL-') && ! str_contains($code, ' ')) {
                $sellers[$code] = $row['live_value'];
            } elseif ($row['type'] === 'store' && str_starts_with($code, 'ST-')) {
                $stores[$code] = $row['live_value'];
            }
        }

        return [$sellers, $stores];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: array<string, int>, 1: list<string>} [workbook region id => region id], names not found
     */
    private function importRegions(array $rows): array
    {
        $ids = $missing = [];

        foreach ($rows as $row) {
            $region = Region::whereRaw('LOWER(name) = ?', [Str::lower($row['region_name'])])->first();

            if (! $region) {
                $missing[] = $row['region_name'];

                continue;
            }

            $region->update([
                'zone' => $row['zone'],
                'latitude' => $this->decimal($row['centroid_lat']),
                'longitude' => $this->decimal($row['centroid_lng']),
            ]);
            $ids[$row['region_id']] = $region->id;
        }

        return [$ids, $missing];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $liveNames
     */
    private function importSellers(array $rows, array $liveNames): int
    {
        foreach ($rows as $row) {
            $merchant = Merchant::where('code', $row['seller_id'])->first()
                ?? (isset($liveNames[$row['seller_id']]) ? Merchant::whereNull('code')->where('business_name', $liveNames[$row['seller_id']])->first() : null)
                ?? Merchant::whereNull('code')->where('business_name', $row['display_name'])->first();

            if ($merchant && $merchant->business_name !== $row['display_name']) {
                $this->renamed++;
            }

            $merchant ??= new Merchant([
                'owner_name' => $row['display_name'],
                'kyc_status' => $row['status'] === 'active' ? Merchant::KYC_APPROVED : Merchant::KYC_PENDING,
                'status' => Merchant::STATUS_ACTIVE,
            ]);

            $merchant->fill([
                'code' => $row['seller_id'],
                'business_name' => $row['display_name'],
                'legal_name' => $row['legal_name'],
                'website' => $row['website'],
                'phone' => $row['hq_phone'] ?? $merchant->phone ?? '',
                'business_type_id' => $this->businessTypeId($row['seller_type']),
            ])->save();
        }

        return count($rows);
    }

    /** "Agrovet (multi-store)" → Agrovet */
    private function businessTypeId(?string $sellerType): ?int
    {
        $name = trim((string) preg_replace('/\s*\(.*\)$/', '', (string) $sellerType));

        return $name === '' ? null : BusinessType::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $regions
     * @param  array<string, string>  $liveNames
     */
    private function importStores(array $rows, array $regions, array $liveNames): int
    {
        $merchants = Merchant::whereNotNull('code')->pluck('id', 'code');

        foreach ($rows as $row) {
            $merchantId = $merchants[$row['seller_id']]
                ?? throw new RuntimeException("Store {$row['store_id']} belongs to unknown seller {$row['seller_id']}.");

            $store = Branch::where('code', $row['store_id'])->first()
                ?? Branch::whereNull('code')->where('merchant_id', $merchantId)
                    ->whereIn('name', array_filter([$liveNames[$row['store_id']] ?? null, $row['store_name']]))
                    ->first();

            if ($store && $store->name !== $row['store_name']) {
                $this->renamed++;
            }

            $store ??= new Branch(['merchant_id' => $merchantId]);

            $store->fill([
                'code' => $row['store_id'],
                'short_code' => $row['store_code'],
                'name' => $row['store_name'],
                'store_type' => $row['store_type'],
                'region_id' => $regions[$row['region_id']] ?? $store->region_id,
                'city' => $row['district'],
                'area' => $row['area'],
                'address' => $row['street_address'],
                'landmark' => $row['landmark'],
                'latitude' => $this->decimal($row['latitude']),
                'longitude' => $this->decimal($row['longitude']),
                'phone' => $row['phone'],
                'whatsapp' => $row['whatsapp'],
                'email' => $row['email'],
                'manager_name' => $row['manager'],
                'opening_hours' => $this->openingHours($row['opening_hours']) ?? $store->opening_hours,
                'is_primary' => $this->bool($row['is_hq']),
                'delivery_radius_km' => $this->decimal($row['delivery_radius_km']),
                'delivery_base_fee' => $this->decimal($row['delivery_base_fee_tzs']),
                'delivery_fee_per_km' => $this->decimal($row['delivery_fee_per_km_tzs']),
                'pickup_available' => $this->bool($row['pickup_available']),
                'cold_chain_capable' => $this->bool($row['cold_chain_capable']),
                'vet_advice_on_site' => $this->bool($row['vet_advice_on_site']),
                'bio' => $row['bio_en'],
                'bio_sw' => $row['bio_sw'],
            ])->save();

            // One head office per seller.
            if ($store->is_primary) {
                Branch::where('merchant_id', $merchantId)->whereKeyNot($store->id)->update(['is_primary' => false]);
            }
        }

        return count($rows);
    }

    /**
     * "Mon-Fri 08:00-17:30; Sat 08:30-14:00; Sun closed" → the stored
     * {"mon": {"open": "08:00", "close": "17:30"}, …, "sun": null} form.
     * Days not mentioned are closed. Null when the text can't be read.
     *
     * @return array<string, array{open: string, close: string}|null>|null
     */
    public function openingHours(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $hours = array_fill_keys(self::DAYS, null);

        foreach (array_filter(array_map('trim', explode(';', $text))) as $part) {
            if (! preg_match('/^([a-z]{3})(?:\s*-\s*([a-z]{3}))?\s+(.+)$/i', $part, $m)) {
                return null;
            }

            $from = array_search(Str::lower($m[1]), self::DAYS, true);
            $to = $m[2] !== '' ? array_search(Str::lower($m[2]), self::DAYS, true) : $from;
            $time = trim($m[3]);

            if ($from === false || $to === false) {
                return null;
            }

            if (Str::lower($time) === 'closed') {
                $day = null;
            } elseif (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $time, $t)) {
                $day = ['open' => Str::padLeft($t[1], 5, '0'), 'close' => Str::padLeft($t[2], 5, '0')];
            } else {
                return null;
            }

            for ($i = $from; $i <= $to; $i++) {
                $hours[self::DAYS[$i]] = $day;
            }
        }

        return $hours;
    }

    /** "5,000" → 5000.0 */
    private function decimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '', (string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function bool(mixed $value): bool
    {
        return $value === true || strtoupper((string) $value) === 'TRUE';
    }
}
