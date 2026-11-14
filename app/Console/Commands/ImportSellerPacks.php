<?php

namespace App\Console\Commands;

use App\Services\SellerPackImporter;
use Illuminate\Console\Command;

/** Loads the seller packs in database/data/sellers/ (seller + stores). Safe to re-run. */
class ImportSellerPacks extends Command
{
    protected $signature = 'marketplace:import-seller-packs {path? : One pack file (default: every file in database/data/sellers)}';

    protected $description = 'Import sellers and their stores from the JSON seller packs';

    public function handle(SellerPackImporter $importer): int
    {
        $paths = $this->argument('path') ? [$this->argument('path')] : SellerPackImporter::paths();

        $rows = array_map(fn (string $path) => array_values($importer->import($path)), $paths);
        $this->table(['Seller', 'Stores'], $rows);
        $this->info('Seller packs imported.');

        return self::SUCCESS;
    }
}
