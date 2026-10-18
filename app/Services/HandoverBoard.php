<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model for the handover centre and the printable handover summary.
 *
 * Outstanding work for a date = that date's instances that are not done,
 * plus instances from the previous carry_over_days days that are still
 * pending. Statuses are the current state; submitted handovers keep their
 * own frozen snapshot (see snapshot()).
 */
class HandoverBoard
{
    /**
     * Outstanding instances sorted so the most urgent are reviewed first:
     * flagged for follow-up, then priority, then reopened, then oldest date.
     *
     * @return Collection<int, ActivityInstance>
     */
    public function outstanding(CarbonImmutable $date, ?int $personId = null): Collection
    {
        return ActivityInstance::query()
            ->where('status', ActivityStatus::Pending)
            ->where(fn (Builder $q) => $q->onDate($date)->orWhere(fn (Builder $c) => $c->carriedOverInto($date)))
            ->tap(fn (Builder $q) => $this->forPerson($q, $personId))
            ->withRowContext()
            ->with(['updates' => fn ($q) => $q->latest('id')->limit(4)])
            ->get()
            ->sortBy(fn (ActivityInstance $i) => [
                $i->requires_follow_up ? 0 : 1,
                $i->definition->priority->rank(),
                $i->wasReopened() ? 0 : 1,
                $i->operational_date->format('Y-m-d'),
                $i->scheduled_at?->format('H:i') ?? '99:99',
            ])
            ->values();
    }

    /**
     * @return Collection<int, ActivityInstance>
     */
    public function completed(CarbonImmutable $date, ?int $personId = null): Collection
    {
        return ActivityInstance::query()
            ->onDate($date)
            ->where('status', ActivityStatus::Done)
            ->tap(fn (Builder $q) => $this->forPerson($q, $personId))
            ->withRowContext()
            ->orderBy('last_updated_at')
            ->get();
    }

    /**
     * Updates recorded during the date (or one shift of it), newest first.
     *
     * @return Collection<int, ActivityUpdate>
     */
    public function journal(CarbonImmutable $date, ?string $shift = null, ?int $personId = null): Collection
    {
        [$start, $end] = $shift && config("operations.shifts.{$shift}")
            ? OpsClock::shiftBounds($date, $shift)
            : OpsClock::dayBounds($date);

        return ActivityUpdate::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when($personId, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->with('instance.definition')
            ->latest('id')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{total: int, done: int, pending: int, not_updated: int, carried_over: int, outstanding: int, updates: int}
     */
    public function counts(CarbonImmutable $date): array
    {
        $day = ActivityInstance::query()->onDate($date)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when status = 'pending' and latest_update_id is not null then 1 else 0 end) as pending")
            ->selectRaw('sum(case when latest_update_id is null then 1 else 0 end) as not_updated')
            ->first();

        $carried = ActivityInstance::query()->carriedOverInto($date)->count();
        [$start, $end] = OpsClock::dayBounds($date);

        return [
            'total' => (int) $day->total,
            'done' => (int) $day->done,
            'pending' => (int) $day->pending,
            'not_updated' => (int) $day->not_updated,
            'carried_over' => $carried,
            'outstanding' => (int) $day->pending + (int) $day->not_updated + $carried,
            'updates' => ActivityUpdate::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
        ];
    }

    /**
     * Frozen copy of the handover state stored on a submitted handover.
     *
     * @return array<string, mixed>
     */
    public function snapshot(CarbonImmutable $date): array
    {
        return [
            'prepared_at' => now()->utc()->toIso8601String(),
            'counts' => $this->counts($date),
            'outstanding' => $this->outstanding($date)->map(fn (ActivityInstance $i) => [
                'instance_id' => $i->id,
                'reference' => $i->reference(),
                'title' => $i->definition->title,
                'operational_date' => $i->operational_date->format('Y-m-d'),
                'state' => $i->displayState(),
                'assigned_to' => $i->assignee?->name,
                'latest_remark' => $i->latestUpdate?->remark,
                'last_updated_by' => $i->latestUpdate?->updater_name,
                'last_updated_at' => $i->latestUpdate?->created_at?->toIso8601String(),
                'reasons' => $i->attentionReasons($date),
            ])->all(),
        ];
    }

    /**
     * @param  Builder<ActivityInstance>  $query
     */
    private function forPerson(Builder $query, ?int $personId): void
    {
        $query->when($personId, fn (Builder $q, int $id) => $q->where(fn (Builder $inner) => $inner
            ->where('assigned_to', $id)
            ->orWhereHas('updates', fn (Builder $u) => $u->where('user_id', $id))));
    }
}
