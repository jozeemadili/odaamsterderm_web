<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\StoreCatalogueStocker;
use Illuminate\Console\Command;

/**
 * Puts every pack size of the catalogue into one store, with the workbook's
 * opening stock for it. Safe to re-run: packs the store already has are
 * left as they are.
 */
class StockStoreFromCatalogue extends Command
{
    protected $signature = 'catalog:stock-store
        {store : Store id or name (e.g. "TEGETA KIBAONI BRANCH")}
        {--workbook-store=ST-AMS-TEG : Workbook store whose Store_Inventory rows give quantities and prices}';

    protected $description = 'Add every catalogue pack size to a store, with opening stock from the workbook';

    public function handle(StoreCatalogueStocker $stocker): int
    {
        $key = $this->argument('store');
        $store = Branch::with('merchant')->find(is_numeric($key) ? (int) $key : null)
            ?? Branch::with('merchant')->where('name', 'like', '%'.$key.'%')->sole();

        $this->info("Stocking {$store->name} ({$store->merchant->business_name})…");
        $counts = $stocker->stock($store, $this->option('workbook-store'));

        $this->table(['What', 'Packs'], collect($counts)->map(fn ($n, $what) => [str_replace('_', ' ', $what), $n])->values()->all());

        return self::SUCCESS;
    }
}
