<?php

namespace App\Console\Commands;

use App\Services\ChickService;
use Illuminate\Console\Command;

/** One-off: hatch dates from the single-seller setup become order days of agents. Safe to re-run. */
class MoveChicksToAgents extends Command
{
    protected $signature = 'chicks:move-to-agents';

    protected $description = 'Move hatch dates of the single-seller chick setup to agents and their contracts';

    public function handle(ChickService $chicks): int
    {
        $r = $chicks->moveToAgents();
        $this->info("Removed {$r['removed']} unbooked sample dates; moved {$r['moved']} dates under {$r['contracts']} contracts.");

        return self::SUCCESS;
    }
}
