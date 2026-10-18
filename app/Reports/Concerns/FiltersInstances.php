<?php

namespace App\Reports\Concerns;

use App\Enums\ActivityStatus;
use App\Models\ActivityInstance;
use App\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

trait FiltersInstances
{
    /**
     * Instances whose operational date falls inside the period, narrowed by
     * activity, category and person. "Person" means the instance was assigned
     * to them or they recorded at least one update on it.
     *
     * @return Builder<ActivityInstance>
     */
    protected function instancesInPeriod(ReportFilters $filters): Builder
    {
        return ActivityInstance::query()
            ->whereBetween('activity_instances.operational_date', [$filters->from->format('Y-m-d'), $filters->to->format('Y-m-d')])
            ->when($filters->definitionId, fn (Builder $q, int $id) => $q->where('activity_instances.activity_definition_id', $id))
            ->when($filters->categoryId, fn (Builder $q, int $id) => $q->whereHas('definition', fn (Builder $d) => $d->where('category_id', $id)))
            ->when($filters->userId, fn (Builder $q, int $id) => $q->where(fn (Builder $inner) => $inner
                ->where('activity_instances.assigned_to', $id)
                ->orWhereHas('updates', fn (Builder $u) => $u->where('user_id', $id))));
    }

    /**
     * Filter on the instance's current state.
     *
     * @param  Builder<ActivityInstance>  $query
     */
    protected function applyCurrentState(Builder $query, ?string $state): void
    {
        match ($state) {
            'done' => $query->where('activity_instances.status', ActivityStatus::Done),
            'pending' => $query->where('activity_instances.status', ActivityStatus::Pending)->whereNotNull('activity_instances.latest_update_id'),
            'not_updated' => $query->whereNull('activity_instances.latest_update_id'),
            default => null,
        };
    }
}
