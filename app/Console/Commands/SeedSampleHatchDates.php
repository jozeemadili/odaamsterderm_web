<?php

namespace App\Console\Commands;

use App\Models\HatchSchedule;
use App\Models\Producer;
use App\Services\ChickService;
use Illuminate\Console\Command;

/**
 * Sample hatch dates for demos: for every active hatchery and each chick
 * type it produces, a hatch every Friday for the next few weeks. Marked as
 * samples; --remove deletes the ones nobody has booked. Safe to re-run.
 */
class SeedSampleHatchDates extends Command
{
    protected $signature = 'chicks:seed-samples {--weeks=4 : Fridays ahead to fill} {--remove : Delete unbooked sample dates instead}';

    protected $description = 'Add (or remove) sample hatch dates for every hatchery and chick type';

    /** Chicks per hatch and sample price per chick, by type. */
    private const PLAN = [
        'broiler' => [6000, 2200],
        'layers' => [3000, 3100],
        'sasso' => [2000, 2800],
        'tanbro' => [2500, 2600],
    ];

    public function handle(ChickService $chicks): int
    {
        if ($this->option('remove')) {
            $removed = HatchSchedule::where('is_sample', true)->whereDoesntHave('orderItems')->delete();
            $this->info("Removed {$removed} unbooked sample hatch dates.");

            return self::SUCCESS;
        }

        $weeks = max(1, (int) $this->option('weeks'));
        $added = 0;

        foreach (Producer::hatcheries()->active()->with('chickTypes')->get() as $i => $hatchery) {
            foreach ($hatchery->chickTypes as $type) {
                [$capacity, $price] = self::PLAN[$type->code] ?? [3000, 2500];

                for ($w = 0; $w < $weeks; $w++) {
                    // Spread hatcheries over the week so dates differ; first hatch at least 5 days away.
                    $date = today()->addDays(5)->next('Friday')->addWeeks($w)->subDays($i % 3);
                    $exists = HatchSchedule::where('producer_id', $hatchery->id)->where('chick_type_id', $type->id)
                        ->whereDate('hatch_date', $date)->exists();
                    if ($exists) {
                        continue;
                    }

                    $schedule = $chicks->saveSchedule([
                        'producer_id' => $hatchery->id,
                        'chick_type_id' => $type->id,
                        'hatch_date' => $date->toDateString(),
                        'capacity' => $capacity,
                        'price_per_chick' => $price,
                        'notes' => 'Sample hatch date',
                    ]);
                    $schedule->update(['is_sample' => true]);
                    $added++;
                }
            }
        }

        $this->info("Added {$added} sample hatch dates.");

        return self::SUCCESS;
    }
}
