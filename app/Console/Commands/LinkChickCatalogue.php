<?php

namespace App\Console\Commands;

use App\Services\ChickService;
use Illuminate\Console\Command;

/** Links day-old chick products to their chick type and marks their makers as hatcheries. Safe to re-run. */
class LinkChickCatalogue extends Command
{
    protected $signature = 'chicks:link-catalogue';

    protected $description = 'Link day-old chick products to chick types (Broiler, Layers, Sasso, Tanbro) and their hatcheries';

    public function handle(ChickService $chicks): int
    {
        $counts = $chicks->linkCatalogue();
        $this->info("Linked {$counts['products']} chick products from {$counts['hatcheries']} hatcheries.");

        return self::SUCCESS;
    }
}
