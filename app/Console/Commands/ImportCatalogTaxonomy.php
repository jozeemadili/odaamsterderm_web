<?php

namespace App\Console\Commands;

use App\Services\AmsterdamCatalogueImporter;
use App\Services\CatalogTaxonomyImporter;
use Illuminate\Console\Command;

/**
 * Loads animal types, life stages, categories, attributes, search keywords,
 * brands and products from the taxonomy workbook, then Amsterdam Group's own
 * catalogue on top (so its descriptions are never overwritten by the
 * workbook's). Safe to re-run: rows are matched on their code and updated
 * in place.
 */
class ImportCatalogTaxonomy extends Command
{
    protected $signature = 'catalog:import-taxonomy {path? : Workbook path (default: database/data/oda-taxonomy.xlsx)}';

    protected $description = 'Import the catalogue (animals, life stages, categories, attributes, keywords, brands, products) from the workbook';

    public function handle(CatalogTaxonomyImporter $importer, AmsterdamCatalogueImporter $catalogue): int
    {
        $path = $this->argument('path') ?? CatalogTaxonomyImporter::defaultPath();
        $counts = $importer->import($path);
        $counts += collect($catalogue->import())->mapWithKeys(fn ($n, $what) => ["amsterdam_catalogue_{$what}" => $n])->all();

        $this->table(['What', 'Rows'], collect($counts)->map(fn ($n, $what) => [str_replace('_', ' ', $what), $n])->values()->all());
        $this->info('Taxonomy imported from '.$path);

        return self::SUCCESS;
    }
}
