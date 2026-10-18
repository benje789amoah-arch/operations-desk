<?php

namespace App\Console\Commands;

use App\Actions\GenerateDailyInstances;
use App\Support\OpsClock;
use Illuminate\Console\Command;

/**
 * Creates the daily activity instances due on one or more operational dates.
 * Safe to run any number of times: an instance is unique per definition and
 * operational date. Scheduled for 00:01 local time in routes/console.php.
 */
class GenerateInstances extends Command
{
    protected $signature = 'operations:generate-instances
        {--date= : Operational date (Y-m-d), defaults to today}
        {--days=1 : Number of days ending at --date}';

    protected $description = 'Create the daily activity instances due on an operational date';

    public function handle(GenerateDailyInstances $generator): int
    {
        $end = OpsClock::safeDate($this->option('date'));
        $days = max(1, (int) $this->option('days'));
        $total = 0;

        foreach (OpsClock::datesBetween($end->subDays($days - 1), $end) as $date) {
            $created = $generator->handle($date);
            $total += $created;
            $this->line(sprintf('%s  %d created', $date->format('Y-m-d'), $created));
        }

        $this->info("Done. {$total} instance(s) created; existing instances were left untouched.");

        return self::SUCCESS;
    }
}
