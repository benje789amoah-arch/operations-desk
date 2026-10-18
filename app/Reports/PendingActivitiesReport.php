<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Reports\Concerns\FiltersInstances;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PendingActivitiesReport extends Report
{
    use FiltersInstances;

    public function key(): string
    {
        return 'pending';
    }

    public function title(): string
    {
        return 'Pending activities report';
    }

    public function description(): string
    {
        return 'Activities that were still outstanding at the reporting cutoff, with the last explanation recorded before it.';
    }

    public function method(): array
    {
        return [
            'The reporting cutoff is the end of the selected end date, or the current time if the end date is today or later.',
            'An activity counts as pending if its last update before the cutoff set it to Pending, or if it had not been updated at all by then.',
            'Remarks, updater and times are those of the last update before the cutoff, so later work does not rewrite the picture at that time.',
            '"Since cutoff" shows whether the activity has been completed after the cutoff.',
        ];
    }

    public function filters(): array
    {
        return ['definition', 'category', 'status', 'user', 'order'];
    }

    public function cutoff(ReportFilters $filters): CarbonImmutable
    {
        $endOfRange = $filters->bounds()[1];
        $now = CarbonImmutable::now()->utc();

        return $endOfRange->lessThan($now) ? $endOfRange : $now;
    }

    public function summary(ReportFilters $filters): array
    {
        $base = $this->base($filters);
        $total = (clone $base)->count();
        $neverUpdated = $this->whereNotUpdatedBy(clone $base, $this->cutoff($filters))->count();
        $resolvedSince = (clone $base)->where('activity_instances.status', ActivityStatus::Done)->count();
        $oldest = (clone $base)->min('activity_instances.operational_date');

        return [
            ['label' => 'Pending at cutoff', 'value' => $total, 'hint' => 'Cutoff '.ops_datetime($this->cutoff($filters)), 'tone' => 'attention'],
            ['label' => 'Not updated by cutoff', 'value' => $neverUpdated, 'tone' => 'neutral'],
            ['label' => 'Pending after an update', 'value' => $total - $neverUpdated, 'tone' => 'attention'],
            ['label' => 'Completed since cutoff', 'value' => $resolvedSince, 'tone' => 'positive'],
            ['label' => 'Oldest outstanding', 'value' => $oldest ? OpsClock::date($oldest)->format('d M Y') : '—'],
        ];
    }

    public function results(ReportFilters $filters): Builder
    {
        return $this->base($filters)
            ->select('activity_instances.*')
            ->selectSub(StatusAt::updateIdBefore($this->cutoff($filters)), 'cutoff_update_id')
            ->withRowContext()
            ->orderBy('activity_instances.operational_date', $filters->order)
            ->orderBy('activity_instances.scheduled_at')
            ->orderBy('activity_instances.id');
    }

    /**
     * Load the update that was current at the cutoff for each row.
     */
    public function decorate(Collection $rows, ReportFilters $filters): void
    {
        $updates = ActivityUpdate::query()->whereIn('id', $rows->pluck('cutoff_update_id')->filter())->get()->keyBy('id');

        $rows->each(fn (ActivityInstance $row) => $row->setRelation('cutoffUpdate', $updates->get($row->cutoff_update_id)));
    }

    public function csvHeadings(ReportFilters $filters): array
    {
        return ['Operational date', 'Reference', 'Activity', 'Category', 'Priority', 'Assigned to', 'State at cutoff', 'Remark at cutoff', 'Last updated by (at cutoff)', 'Last updated at (at cutoff)', 'Days outstanding at cutoff', 'Current status', 'Reporting cutoff'];
    }

    /**
     * @param  ActivityInstance  $row
     */
    public function csvRow(mixed $row, ReportFilters $filters): array
    {
        $atCutoff = $row->getRelation('cutoffUpdate');
        $cutoff = $this->cutoff($filters);

        return [
            $row->operational_date->format('Y-m-d'),
            $row->reference(),
            $row->definition->title,
            $row->definition->category?->name,
            $row->definition->priority->label(),
            $row->assignee?->name,
            $atCutoff ? 'Pending' : 'Not updated',
            $atCutoff?->remark,
            $atCutoff?->updater_name,
            $atCutoff ? ops_datetime($atCutoff->created_at, true) : null,
            $this->daysOutstanding($row, $cutoff),
            $row->hasUpdates() ? $row->status->label() : 'Not updated',
            ops_datetime($cutoff, true),
        ];
    }

    public function daysOutstanding(ActivityInstance $row, CarbonImmutable $cutoff): int
    {
        return (int) OpsClock::date($row->operational_date)->diffInDays(OpsClock::local($cutoff)->startOfDay());
    }

    /**
     * @return Builder<ActivityInstance>
     */
    private function base(ReportFilters $filters): Builder
    {
        $cutoff = $this->cutoff($filters);
        $status = StatusAt::statusOf(StatusAt::updateIdBefore($cutoff));

        $query = $this->instancesInPeriod($filters)
            // Instances created after the cutoff were not on the register at that time.
            ->where('activity_instances.created_at', '<', $cutoff)
            // No update before the cutoff counts as pending ("not updated").
            ->whereRaw("coalesce(({$status->toSql()}), 'pending') = 'pending'", $status->getBindings());

        match ($filters->status) {
            'not_updated' => $this->whereNotUpdatedBy($query, $cutoff),
            'pending' => $query->where($status, '=', ActivityStatus::Pending->value),
            'done' => $query->whereRaw('1 = 0'),
            default => null,
        };

        return $query;
    }

    /**
     * @param  Builder<ActivityInstance>  $query
     * @return Builder<ActivityInstance>
     */
    private function whereNotUpdatedBy(Builder $query, CarbonImmutable $cutoff): Builder
    {
        $latest = StatusAt::updateIdBefore($cutoff);

        return $query->whereRaw("({$latest->toSql()}) is null", $latest->getBindings());
    }
}
