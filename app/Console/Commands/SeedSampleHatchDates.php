<?php

namespace App\Console\Commands;

use App\Models\ChickContract;
use App\Models\ChickType;
use App\Models\HatchSchedule;
use App\Models\Producer;
use App\Services\ChickService;
use Illuminate\Console\Command;

/**
 * Sample chick sales for demos: the platform's own agent (Amsterdam Group)
 * gets contracts with a few hatcheries (Monday and Thursday, 100 boxes),
 * and their order days are marked as samples. --remove deletes the unbooked
 * sample days and the sample contracts left without days. Safe to re-run.
 */
class SeedSampleHatchDates extends Command
{
    protected $signature = 'chicks:seed-samples {--remove : Delete unbooked sample order days instead}';

    protected $description = 'Add (or remove) sample chick contracts and order days for the platform agent';

    /** Hatchery code => [chick type code, price per chick]. */
    private const PLAN = [
        ['BR-HARS', 'broiler', 2200],
        ['BR-KIBO', 'broiler', 2150],
        ['BR-INTR', 'layers', 3100],
        ['BR-INTR', 'tanbro', 2600],
        ['BR-SIL', 'sasso', 2800],
    ];

    public function handle(ChickService $chicks): int
    {
        if ($this->option('remove')) {
            $removed = HatchSchedule::where('is_sample', true)->whereDoesntHave('orderItems')->delete();
            // Sample contracts stop; one with real bookings is kept but inactive.
            $contracts = ChickContract::where('is_sample', true)->get();
            foreach ($contracts as $contract) {
                $contract->orderDays()->exists() ? $contract->update(['is_active' => false]) : $contract->delete();
            }
            $this->info("Removed {$removed} unbooked sample order days and {$contracts->count()} sample contracts.");

            return self::SUCCESS;
        }

        $agent = $chicks->platformAgent();
        if (! $agent) {
            $this->error('The platform agent (Amsterdam Group) was not found.');

            return self::FAILURE;
        }

        $added = 0;
        foreach (self::PLAN as [$producerCode, $typeCode, $price]) {
            $producer = Producer::where('code', $producerCode)->first();
            $type = ChickType::where('code', $typeCode)->first();
            if (! $producer || ! $type) {
                continue;
            }

            if (ChickContract::where('merchant_id', $agent->id)->where('producer_id', $producer->id)->where('chick_type_id', $type->id)->exists()) {
                continue; // a real (or earlier sample) contract is already there
            }

            $contract = ChickContract::create([
                'merchant_id' => $agent->id,
                'producer_id' => $producer->id,
                'chick_type_id' => $type->id,
                'weekdays' => ChickContract::DEFAULT_WEEKDAYS,
                'cap_boxes' => ChickContract::DEFAULT_CAP_BOXES,
                'price_per_chick' => $price,
                'is_active' => true,
                'is_sample' => true,
            ]);
            $added += $chicks->generateDays($contract);
        }

        $this->info("{$added} sample order days ready for {$agent->business_name}.");

        return self::SUCCESS;
    }
}
