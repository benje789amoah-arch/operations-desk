<?php

namespace App\Reports;

use App\Reports\Concerns\FiltersInstances;
use App\Support\Ability;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompletionTrendReport extends Report
{
    use FiltersInstances;

    public function key(): string
    {
        return 'trend';
    }

    public function title(): string
    {
        return 'Completion trend report';
    }

    public function description(): string
    {
        return 'Daily counts of activity instances that were done, pending or not updated at the close of each operational day.';
    }

    public function method(): array
    {
        return [
            'Each day counts the activity instances scheduled for that operational date.',
            'Done / Pending / Not updated are the states at the close of that day (the last update made before local midnight). Work finished on a later day does not change an earlier day.',
            'Completion rate = done at close ÷ instances scheduled. Days with nothing scheduled, and today (not yet closed), are excluded from the summary rates and averages.',
            'Activity, category and personnel filters narrow which instances are counted.',
        ];
    }

    public function ability(): ?string
    {
        return Ability::VIEW_MANAGEMENT_REPORTS;
    }

    public function filters(): array
    {
        return ['definition', 'category', 'user'];
    }

    /**
     * Rates and averages only use days that have closed: today is still in
     * progress, so its "at close" figures are not final yet.
     */
    public function summary(ReportFilters $filters): array
    {
        $days = $this->results($filters);
        $closedDays = $days->where('total', '>', 0)->filter(fn ($d) => $d->date->lt(OpsClock::today()));
        $total = $closedDays->sum('total');
        $done = $closedDays->sum('done');
        $includesToday = $filters->to->greaterThanOrEqualTo(OpsClock::today()) && $filters->from->lessThanOrEqualTo(OpsClock::today());

        $worst = $closedDays->sortBy('rate')->first();

        return [
            ['label' => 'Instances scheduled', 'value' => $days->sum('total'), 'hint' => $days->where('total', '>', 0)->count().' of '.$days->count().' days had activities'],
            ['label' => 'Completion at close', 'value' => $total ? round($done / $total * 100, 1).'%' : '—', 'hint' => $done.' of '.$total.' done on the day'.($includesToday ? ' · today excluded' : ''), 'tone' => 'positive'],
            ['label' => 'Avg. pending at close', 'value' => $closedDays->count() ? round($closedDays->avg(fn ($d) => $d->pending + $d->not_updated), 1) : '—', 'hint' => 'Per closed day', 'tone' => 'attention'],
            ['label' => 'Lowest completion day', 'value' => $worst ? $worst->date->format('d M') : '—', 'hint' => $worst ? $worst->rate.'% done at close' : null],
        ];
    }

    /**
     * One row per date in the period (including days with nothing scheduled).
     *
     * @return Collection<int, object{date: CarbonImmutable, total: int, done: int, pending: int, not_updated: int, rate: float}>
     */
    public function results(ReportFilters $filters): Collection
    {
        $instances = $this->instancesInPeriod($filters)->toBase()
            ->select('activity_instances.operational_date')
            ->selectSub(StatusAt::statusOf(StatusAt::closeOfDayUpdateId()), 'status_at_close');

        $byDate = DB::query()->fromSub($instances, 'closing')
            ->select('operational_date')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status_at_close = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when status_at_close = 'pending' then 1 else 0 end) as pending")
            ->selectRaw('sum(case when status_at_close is null then 1 else 0 end) as not_updated')
            ->groupBy('operational_date')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->operational_date, 0, 10));

        return collect(OpsClock::datesBetween($filters->from, $filters->to))
            ->when($filters->order === 'desc', fn (Collection $dates) => $dates->reverse()->values())
            ->map(function ($date) use ($byDate) {
                $row = $byDate->get($date->format('Y-m-d'));
                $total = (int) ($row->total ?? 0);
                $done = (int) ($row->done ?? 0);

                return (object) [
                    'date' => $date,
                    'total' => $total,
                    'done' => $done,
                    'pending' => (int) ($row->pending ?? 0),
                    'not_updated' => (int) ($row->not_updated ?? 0),
                    'rate' => $total ? round($done / $total * 100, 1) : 0.0,
                ];
            });
    }

    public function csvHeadings(ReportFilters $filters): array
    {
        return ['Operational date', 'Instances scheduled', 'Done at close', 'Pending at close', 'Not updated at close', 'Completion rate %'];
    }

    public function csvRow(mixed $row, ReportFilters $filters): array
    {
        return [$row->date->format('Y-m-d'), $row->total, $row->done, $row->pending, $row->not_updated, $row->total ? $row->rate : null];
    }
}
