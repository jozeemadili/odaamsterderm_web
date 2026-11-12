<?php

namespace App\Console\Commands;

use App\Services\AmsterdamCatalogueImporter;
use Illuminate\Console\Command;

/**
 * Loads Amsterdam Group's product catalogue on its own. catalog:import-taxonomy
 * runs it too, after the workbook. Safe to re-run.
 */
class ImportAmsterdamCatalogue extends Command
{
    protected $signature = 'catalog:import-amsterdam';

    protected $description = "Import Amsterdam Group's product catalogue (descriptions, pack sizes, photos)";

    public function handle(AmsterdamCatalogueImporter $importer): int
    {
        $counts = $importer->import();

        $this->table(['What', 'Rows'], collect($counts)->map(fn ($n, $what) => [str_replace('_', ' ', $what), $n])->values()->all());
        $this->info('Amsterdam catalogue imported.');

        return self::SUCCESS;
    }
}
