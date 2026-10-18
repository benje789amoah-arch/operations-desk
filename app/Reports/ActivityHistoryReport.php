<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Models\ActivityUpdate;
use Illuminate\Database\Eloquent\Builder;

class ActivityHistoryReport extends Report
{
    public function key(): string
    {
        return 'history';
    }

    public function title(): string
    {
        return 'Activity history report';
    }

    public function description(): string
    {
        return 'Every update in the period in chronological order: status change, remark, who made it and when.';
    }

    public function method(): array
    {
        return [
            'With "Operational date" as the date basis, includes updates made on activities whose operational date falls in the period, even if the update was recorded later.',
            'With "Time recorded" as the basis, includes updates whose server timestamp falls inside the period, whichever day the activity belongs to.',
            'The status filter applies to the new status set by each update. The personnel filter applies to the person who made the update.',
            'Updater details are shown as they were when the update was recorded.',
        ];
    }

    public function filters(): array
    {
        return ['definition', 'category', 'status', 'user', 'basis', 'order'];
    }

    public function summary(ReportFilters $filters): array
    {
        $totals = $this->base($filters)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(distinct activity_updates.activity_instance_id) as instances')
            ->selectRaw('count(distinct activity_updates.user_id) as people')
            ->selectRaw("sum(case when activity_updates.new_status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when activity_updates.new_status = 'pending' then 1 else 0 end) as pending")
            ->selectRaw("sum(case when activity_updates.previous_status = 'done' and activity_updates.new_status = 'pending' then 1 else 0 end) as reopened")
            ->first();

        return [
            ['label' => 'Updates recorded', 'value' => (int) $totals->total],
            ['label' => 'Activities updated', 'value' => (int) $totals->instances, 'hint' => 'Distinct activity instances'],
            ['label' => 'Personnel involved', 'value' => (int) $totals->people],
            ['label' => 'Done updates', 'value' => (int) $totals->done, 'tone' => 'positive'],
            ['label' => 'Pending updates', 'value' => (int) $totals->pending, 'hint' => (int) $totals->reopened.' reopened', 'tone' => 'attention'],
        ];
    }

    public function results(ReportFilters $filters): Builder
    {
        return $this->base($filters)
            ->select('activity_updates.*')
            ->with('instance.definition.category')
            ->orderBy('activity_updates.created_at', $filters->order)
            ->orderBy('activity_updates.id', $filters->order);
    }

    public function csvHeadings(ReportFilters $filters): array
    {
        return ['Update ID', 'Recorded at', 'Operational date', 'Activity reference', 'Activity', 'Category', 'Event', 'Previous status', 'New status', 'Remark', 'Supporting details', 'Flagged for follow-up', 'Updated by', 'Staff number', 'Designation', 'Role'];
    }

    /**
     * @param  ActivityUpdate  $row
     */
    public function csvRow(mixed $row, ReportFilters $filters): array
    {
        return [
            sprintf('UPD-%06d', $row->id),
            ops_datetime($row->created_at, true),
            $row->instance->operational_date->format('Y-m-d'),
            $row->instance->reference(),
            $row->instance->definition->title,
            $row->instance->definition->category?->name,
            $row->eventLabel(),
            $row->previous_status?->label() ?? 'Not updated',
            $row->new_status->label(),
            $row->remark,
            $row->supporting_details,
            $row->requires_follow_up ? 'Yes' : 'No',
            $row->updater_name,
            $row->updater_staff_number,
            $row->updater_designation,
            $row->updater_role->label(),
        ];
    }

    /**
     * @return Builder<ActivityUpdate>
     */
    private function base(ReportFilters $filters): Builder
    {
        [$start, $end] = $filters->bounds();

        return ActivityUpdate::query()
            ->join('activity_instances', 'activity_instances.id', '=', 'activity_updates.activity_instance_id')
            ->join('activity_definitions', 'activity_definitions.id', '=', 'activity_instances.activity_definition_id')
            ->when($filters->basis === 'recorded',
                fn (Builder $q) => $q->where('activity_updates.created_at', '>=', $start)->where('activity_updates.created_at', '<', $end),
                fn (Builder $q) => $q->whereBetween('activity_instances.operational_date', [$filters->from->format('Y-m-d'), $filters->to->format('Y-m-d')]))
            ->when($filters->definitionId, fn (Builder $q, int $id) => $q->where('activity_instances.activity_definition_id', $id))
            ->when($filters->categoryId, fn (Builder $q, int $id) => $q->where('activity_definitions.category_id', $id))
            ->when($filters->userId, fn (Builder $q, int $id) => $q->where('activity_updates.user_id', $id))
            ->when(in_array($filters->status, ['done', 'pending'], true), fn (Builder $q) => $q->where('activity_updates.new_status', ActivityStatus::from($filters->status)))
            ->when($filters->status === 'not_updated', fn (Builder $q) => $q->whereRaw('1 = 0'));
    }
}
