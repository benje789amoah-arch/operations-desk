<?php

namespace App\Services;

use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Reports\CompletionTrendReport;
use App\Reports\ReportFilters;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Read model for the overview dashboard. Metric definitions:
 *
 *  expected     instances whose operational date is today
 *  done         of those, currently Done
 *  pending      of those, currently Pending after at least one update
 *  not_updated  of those, with no update yet
 *  updated      distinct instances (any operational date) that received at
 *               least one update today; several updates to one activity count once
 */
class DeskOverview
{
    public function __construct(private readonly HandoverBoard $board) {}

    /**
     * @return array<string, int|float>
     */
    public function metrics(CarbonImmutable $today): array
    {
        $counts = $this->board->counts($today);
        [$start, $end] = OpsClock::dayBounds($today);

        $updatedToday = ActivityUpdate::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->distinct()
            ->count('activity_instance_id');

        return [
            'expected' => $counts['total'],
            'done' => $counts['done'],
            'pending' => $counts['pending'],
            'not_updated' => $counts['not_updated'],
            'carried_over' => $counts['carried_over'],
            'updated' => $updatedToday,
            'updates' => $counts['updates'],
            'completion' => $counts['total'] ? round($counts['done'] / $counts['total'] * 100, 1) : 0,
        ];
    }

    /**
     * @return Collection<int, ActivityInstance>
     */
    public function todaysActivities(CarbonImmutable $today): Collection
    {
        return ActivityInstance::query()
            ->onDate($today)
            ->withRowContext()
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Outstanding work matching a configured attention rule (see
     * ActivityInstance::attentionReasons): critical priority, flagged for
     * follow-up, reopened, carried over from an earlier day, or past due.
     *
     * @return BaseCollection<int, ActivityInstance>
     */
    public function requiringAttention(CarbonImmutable $today): BaseCollection
    {
        return $this->board->outstanding($today)
            ->filter(fn (ActivityInstance $i) => $i->attentionReasons($today) !== [])
            ->values();
    }

    /**
     * @return Collection<int, ActivityUpdate>
     */
    public function recentUpdates(int $limit = 8): Collection
    {
        return ActivityUpdate::query()->with('instance.definition')->latest('id')->limit($limit)->get();
    }

    /**
     * Completion at close of day for the last $days days (today included).
     *
     * @return BaseCollection<int, object>
     */
    public function trend(CarbonImmutable $today, int $days = 14): BaseCollection
    {
        return app(CompletionTrendReport::class)->results(
            new ReportFilters(type: 'trend', from: $today->subDays($days - 1), to: $today),
        );
    }
}
