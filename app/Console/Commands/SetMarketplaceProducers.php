<?php

namespace App\Console\Commands;

use App\Models\Producer;
use Illuminate\Console\Command;

/**
 * Shows only the given producers' products on the marketplace and hides
 * every other producer's (they stay in the catalogue and in stock). Admins
 * can switch single producers on the Producers screen.
 */
class SetMarketplaceProducers extends Command
{
    protected $signature = 'catalog:marketplace-producers {codes* : Producer codes to show, e.g. BR-AMS BR-SIL}';

    protected $description = "Show only these producers' products on the marketplace";

    public function handle(): int
    {
        $codes = $this->argument('codes');
        $unknown = array_diff($codes, Producer::whereIn('code', $codes)->pluck('code')->all());

        if ($unknown) {
            $this->error('Unknown producer codes: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $shown = Producer::whereIn('code', $codes)->get();
        Producer::query()->update(['on_marketplace' => false]);
        Producer::whereIn('code', $codes)->update(['on_marketplace' => true]);

        $this->info('Shown on the marketplace: '.$shown->pluck('name')->join(', ').'. '.(Producer::count() - $shown->count()).' other producers hidden.');

        return self::SUCCESS;
    }
}
