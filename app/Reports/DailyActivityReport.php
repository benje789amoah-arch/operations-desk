<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Models\ActivityInstance;
use App\Reports\Concerns\FiltersInstances;
use Illuminate\Database\Eloquent\Builder;

class DailyActivityReport extends Report
{
    use FiltersInstances;

    public function key(): string
    {
        return 'daily';
    }

    public function title(): string
    {
        return 'Daily activity report';
    }

    public function description(): string
    {
        return 'Every activity scheduled in the period with its status, latest remark and who last updated it.';
    }

    public function method(): array
    {
        return [
            'Includes activity instances whose operational date falls inside the period.',
            '"Status at close" is the status recorded by the last update made before the end of that operational day; later updates do not change it.',
            '"Current status" is the latest state now. The status filter applies to the current status.',
            'The personnel filter keeps activities assigned to, or updated by, that person.',
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $base = $this->instancesInPeriod($filters);
        $this->applyCurrentState($base, $filters->status);

        $totals = (clone $base)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when status = 'pending' and latest_update_id is not null then 1 else 0 end) as pending")
            ->selectRaw('sum(case when latest_update_id is null then 1 else 0 end) as not_updated')
            ->first();

        $doneAtClose = (clone $base)->where(StatusAt::statusOf(StatusAt::closeOfDayUpdateId()), '=', ActivityStatus::Done->value)->count();

        $total = (int) $totals->total;

        return [
            ['label' => 'Activities in period', 'value' => $total, 'hint' => $filters->days().' day'.($filters->days() === 1 ? '' : 's')],
            ['label' => 'Done now', 'value' => (int) $totals->done, 'hint' => $total ? round($totals->done / $total * 100, 1).'% of activities' : 'No activities', 'tone' => 'positive'],
            ['label' => 'Done by close of day', 'value' => $doneAtClose, 'hint' => $total ? round($doneAtClose / $total * 100, 1).'% completed on the day' : '—', 'tone' => 'positive'],
            ['label' => 'Pending (updated)', 'value' => (int) $totals->pending, 'tone' => 'attention'],
            ['label' => 'Not updated', 'value' => (int) $totals->not_updated, 'tone' => 'neutral'],
        ];
    }

    public function results(ReportFilters $filters): Builder
    {
        $query = $this->instancesInPeriod($filters)
            ->select('activity_instances.*')
            ->selectSub(StatusAt::statusOf(StatusAt::closeOfDayUpdateId()), 'status_at_close')
            ->withCount('updates')
            ->withRowContext()
            ->with('latestUpdate')
            ->orderBy('activity_instances.operational_date', $filters->order)
            ->orderBy('activity_instances.scheduled_at')
            ->orderBy('activity_instances.id');

        $this->applyCurrentState($query, $filters->status);

        return $query;
    }

    public function csvHeadings(ReportFilters $filters): array
    {
        return ['Operational date', 'Reference', 'Activity', 'Category', 'Priority', 'Assigned to', 'Status at close of day', 'Current status', 'Latest remark', 'Last updated by', 'Last updated at', 'Number of updates'];
    }

    /**
     * @param  ActivityInstance  $row
     */
    public function csvRow(mixed $row, ReportFilters $filters): array
    {
        return [
            $row->operational_date->format('Y-m-d'),
            $row->reference(),
            $row->definition->title,
            $row->definition->category?->name,
            $row->definition->priority->label(),
            $row->assignee?->name,
            $row->status_at_close ? ActivityStatus::from($row->status_at_close)->label() : 'Not updated',
            $row->hasUpdates() ? $row->status->label() : 'Not updated',
            $row->latestUpdate?->remark,
            $row->latestUpdate?->updater_name,
            $row->latestUpdate ? ops_datetime($row->latestUpdate->created_at, true) : null,
            $row->updates_count,
        ];
    }
}
