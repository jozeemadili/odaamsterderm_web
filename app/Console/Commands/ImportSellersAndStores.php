<?php

namespace App\Console\Commands;

use App\Services\CatalogTaxonomyImporter;
use App\Services\SellerStoreImporter;
use Illuminate\Console\Command;

/**
 * Loads sellers, regions and stores from the taxonomy workbook. Safe to
 * re-run: rows are matched on their workbook code and updated in place.
 */
class ImportSellersAndStores extends Command
{
    protected $signature = 'marketplace:import-sellers {path? : Workbook path (default: database/data/oda-taxonomy.xlsx)}';

    protected $description = 'Import sellers, regions and stores (delivery, cold chain, bios) from the taxonomy workbook';

    public function handle(SellerStoreImporter $importer): int
    {
        $path = $this->argument('path') ?? CatalogTaxonomyImporter::defaultPath();
        $counts = $importer->import($path);

        $missing = $counts['regions_not_found'] ?? [];
        unset($counts['regions_not_found']);

        $this->table(['What', 'Rows'], collect($counts)->map(fn ($n, $what) => [str_replace('_', ' ', $what), $n])->values()->all());

        if ($missing) {
            $this->warn('Regions not found (add them, then re-run): '.implode(', ', $missing));
        }

        $this->info('Sellers and stores imported from '.$path);

        return self::SUCCESS;
    }
}
