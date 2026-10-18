<?php

namespace App\Reports;

use Illuminate\Support\Arr;

final class ReportRegistry
{
    /**
     * @return array<string, class-string<Report>>
     */
    public static function all(): array
    {
        return [
            'daily' => DailyActivityReport::class,
            'history' => ActivityHistoryReport::class,
            'pending' => PendingActivitiesReport::class,
            'personnel' => PersonnelActivityReport::class,
            'trend' => CompletionTrendReport::class,
        ];
    }

    public static function make(string $key): Report
    {
        return app(Arr::get(self::all(), $key, DailyActivityReport::class));
    }
}
