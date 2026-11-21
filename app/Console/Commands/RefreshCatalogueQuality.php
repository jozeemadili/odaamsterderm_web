<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RefreshCatalogueQuality extends Command
{
    protected $signature = 'catalog:quality';

    protected $description = 'Recompute every catalogue product\'s completeness score and missing required fields';

    public function handle(): int
    {
        $missing = 0;
        $total = 0;
        $sum = 0;

        Product::query()->with(['category.parent', 'animalTypes', 'animalType', 'lifeStages', 'variants', 'media'])
            ->chunkById(200, function ($products) use (&$missing, &$total, &$sum) {
                foreach ($products as $product) {
                    $check = $product->refreshQuality();
                    $total++;
                    $sum += $check['score'];
                    $missing += $check['missing'] ? 1 : 0;
                }
            });

        $this->info(sprintf('%d products · average %d%% complete · %d missing required details', $total, $total ? round($sum / $total) : 0, $missing));

        return self::SUCCESS;
    }
}
