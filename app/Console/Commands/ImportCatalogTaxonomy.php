<?php

namespace App\Console\Commands;

use App\Services\CatalogTaxonomyImporter;
use Illuminate\Console\Command;

/**
 * Loads animal types, life stages, categories, attributes, search keywords,
 * brands and products from the taxonomy workbook. Safe to re-run: rows are
 * matched on their workbook code and updated in place.
 */
class ImportCatalogTaxonomy extends Command
{
    protected $signature = 'catalog:import-taxonomy {path? : Workbook path (default: database/data/oda-taxonomy.xlsx)}';

    protected $description = 'Import the catalogue (animals, life stages, categories, attributes, keywords, brands, products) from the workbook';

    public function handle(CatalogTaxonomyImporter $importer): int
    {
        $path = $this->argument('path') ?? CatalogTaxonomyImporter::defaultPath();
        $counts = $importer->import($path);

        $this->table(['What', 'Rows'], collect($counts)->map(fn ($n, $what) => [str_replace('_', ' ', $what), $n])->values()->all());
        $this->info('Taxonomy imported from '.$path);

        return self::SUCCESS;
    }
}
