<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A report is a set of database queries driven entirely by ReportFilters.
 * results() returns either a query builder (paginated on screen, chunked for
 * CSV) or an already-aggregated collection for small summary reports.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Plain-language rules explaining exactly how the report is calculated.
     *
     * @return array<int, string>
     */
    abstract public function method(): array;

    /**
     * @return array<int, array{label: string, value: string|int, hint?: string, tone?: string}>
     */
    abstract public function summary(ReportFilters $filters): array;

    abstract public function results(ReportFilters $filters): Builder|Collection;

    /**
     * @return array<int, string>
     */
    abstract public function csvHeadings(ReportFilters $filters): array;

    /**
     * @return array<int, scalar|null>
     */
    abstract public function csvRow(mixed $row, ReportFilters $filters): array;

    /**
     * Gate ability required to run this report, if any beyond signing in.
     */
    public function ability(): ?string
    {
        return null;
    }

    public function availableTo(User $user): bool
    {
        return $this->ability() === null || $user->can($this->ability());
    }

    /**
     * Attach anything a page of rows needs that cannot be eager loaded.
     *
     * @param  Collection<int, mixed>  $rows
     */
    public function decorate(Collection $rows, ReportFilters $filters): void {}

    /**
     * Which filters the UI should offer for this report.
     *
     * @return array<int, string>
     */
    public function filters(): array
    {
        return ['definition', 'category', 'status', 'user'];
    }
}
