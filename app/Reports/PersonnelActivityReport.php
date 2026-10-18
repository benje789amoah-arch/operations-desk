<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Models\ActivityUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * With a person selected: every update they recorded in the period.
 * Without one (supervisors and administrators only): one row per person.
 */
class PersonnelActivityReport extends Report
{
    public function key(): string
    {
        return 'personnel';
    }

    public function title(): string
    {
        return 'Personnel activity report';
    }

    public function description(): string
    {
        return 'What each team member updated in the period: distinct activities, Done and Pending updates, and when.';
    }

    public function method(): array
    {
        return [
            'Uses the server time each update was recorded, so the period reflects when the person did the work.',
            '"Activities updated" counts distinct activity instances; several updates to the same activity count once.',
            'Done and Pending counts are counts of updates, by the status each update set.',
            'Team members see only their own activity; supervisors and administrators can review anyone.',
        ];
    }

    public function filters(): array
    {
        return ['definition', 'category', 'status', 'user', 'order'];
    }

    public function summary(ReportFilters $filters): array
    {
        $totals = $this->base($filters)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(distinct activity_updates.activity_instance_id) as instances')
            ->selectRaw('count(distinct activity_updates.user_id) as people')
            ->selectRaw("sum(case when activity_updates.new_status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when activity_updates.new_status = 'pending' then 1 else 0 end) as pending")
            ->selectRaw('min(activity_updates.created_at) as first_at')
            ->selectRaw('max(activity_updates.created_at) as last_at')
            ->first();

        return [
            ['label' => $filters->userId ? 'Updates recorded' : 'Updates by the team', 'value' => (int) $totals->total, 'hint' => $filters->userId ? null : (int) $totals->people.' people'],
            ['label' => 'Activities updated', 'value' => (int) $totals->instances, 'hint' => 'Distinct activity instances'],
            ['label' => 'Done updates', 'value' => (int) $totals->done, 'tone' => 'positive'],
            ['label' => 'Pending updates', 'value' => (int) $totals->pending, 'tone' => 'attention'],
            ['label' => 'Last update', 'value' => $totals->last_at ? ops_datetime(Carbon::parse($totals->last_at, 'UTC')) : '—', 'hint' => $totals->first_at ? 'First '.ops_datetime(Carbon::parse($totals->first_at, 'UTC')) : null],
        ];
    }

    public function results(ReportFilters $filters): Builder|Collection
    {
        if ($filters->userId) {
            return $this->base($filters)
                ->select('activity_updates.*')
                ->with('instance.definition.category')
                ->orderBy('activity_updates.created_at', $filters->order)
                ->orderBy('activity_updates.id', $filters->order);
        }

        $rows = $this->base($filters)->toBase()
            ->select('activity_updates.user_id')
            ->selectRaw('count(*) as updates')
            ->selectRaw('count(distinct activity_updates.activity_instance_id) as instances')
            ->selectRaw("sum(case when activity_updates.new_status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when activity_updates.new_status = 'pending' then 1 else 0 end) as pending")
            ->selectRaw('max(activity_updates.created_at) as last_at')
            ->groupBy('activity_updates.user_id')
            ->orderByDesc('updates')
            ->orderBy('activity_updates.user_id')
            ->get();

        $people = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(fn (object $row) => (object) [
            'user' => $people->get($row->user_id),
            'updates' => (int) $row->updates,
            'instances' => (int) $row->instances,
            'done' => (int) $row->done,
            'pending' => (int) $row->pending,
            'last_at' => $row->last_at ? Carbon::parse($row->last_at, 'UTC') : null,
        ]);
    }

    public function csvHeadings(ReportFilters $filters): array
    {
        return $filters->userId
            ? ['Recorded at', 'Person', 'Staff number', 'Designation', 'Activity reference', 'Activity', 'Operational date', 'Previous status', 'New status', 'Remark']
            : ['Person', 'Staff number', 'Role', 'Updates', 'Activities updated', 'Done updates', 'Pending updates', 'Last update'];
    }

    public function csvRow(mixed $row, ReportFilters $filters): array
    {
        if ($row instanceof ActivityUpdate) {
            return [
                ops_datetime($row->created_at, true),
                $row->updater_name,
                $row->updater_staff_number,
                $row->updater_designation,
                $row->instance->reference(),
                $row->instance->definition->title,
                $row->instance->operational_date->format('Y-m-d'),
                $row->previous_status?->label() ?? 'Not updated',
                $row->new_status->label(),
                $row->remark,
            ];
        }

        return [
            $row->user?->name,
            $row->user?->staff_number,
            $row->user?->role instanceof Role ? $row->user->role->label() : null,
            $row->updates,
            $row->instances,
            $row->done,
            $row->pending,
            $row->last_at ? ops_datetime($row->last_at, true) : null,
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
            ->where('activity_updates.created_at', '>=', $start)
            ->where('activity_updates.created_at', '<', $end)
            ->when($filters->userId, fn (Builder $q, int $id) => $q->where('activity_updates.user_id', $id))
            ->when($filters->definitionId, fn (Builder $q, int $id) => $q->where('activity_instances.activity_definition_id', $id))
            ->when($filters->categoryId, fn (Builder $q, int $id) => $q->where('activity_definitions.category_id', $id))
            ->when(in_array($filters->status, ['done', 'pending'], true), fn (Builder $q) => $q->where('activity_updates.new_status', ActivityStatus::from($filters->status)))
            ->when($filters->status === 'not_updated', fn (Builder $q) => $q->whereRaw('1 = 0'));
    }
}
