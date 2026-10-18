<?php

namespace App\Reports;

use App\Models\ActivityDefinition;
use App\Models\Category;
use App\Models\User;
use App\Rules\MaxDateRange;
use App\Support\Ability;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Validated, authorisation-scoped report parameters. The same rules are used
 * by the Livewire reporting centre and by the CSV export endpoint, so an
 * export always matches what was on screen.
 */
final class ReportFilters
{
    public const STATUSES = ['done' => 'Done', 'pending' => 'Pending (updated)', 'not_updated' => 'Not updated'];

    public function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $definitionId = null,
        public readonly ?string $status = null,
        public readonly ?int $userId = null,
        public readonly ?int $categoryId = null,
        public readonly string $basis = 'operational',
        public readonly string $order = 'asc',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(ReportRegistry::all()))],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', new MaxDateRange('from', config('operations.max_report_range_days'))],
            'definition' => ['nullable', 'integer', Rule::exists('activity_definitions', 'id')],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'user' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'basis' => ['nullable', Rule::in(['operational', 'recorded'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'from.required' => 'Choose a start date.',
            'to.required' => 'Choose an end date.',
            'from.date_format' => 'The start date must be a valid date.',
            'to.date_format' => 'The end date must be a valid date.',
            'to.after_or_equal' => 'The end date cannot be earlier than the start date.',
        ];
    }

    /**
     * Build filters from validated input, applying what the viewer may see.
     * Team members without the team-activity permission only ever see their
     * own personnel report, whatever person was requested.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input, User $viewer): self
    {
        $type = $input['type'];
        $userId = filled($input['user'] ?? null) ? (int) $input['user'] : null;

        if ($type === 'personnel' && $viewer->cannot(Ability::VIEW_TEAM_ACTIVITY)) {
            $userId = $viewer->id;
        }

        return new self(
            type: $type,
            from: OpsClock::date($input['from']),
            to: OpsClock::date($input['to']),
            definitionId: filled($input['definition'] ?? null) ? (int) $input['definition'] : null,
            status: ($input['status'] ?? null) ?: null,
            userId: $userId,
            categoryId: filled($input['category'] ?? null) ? (int) $input['category'] : null,
            basis: ($input['basis'] ?? null) ?: 'operational',
            order: ($input['order'] ?? null) ?: 'asc',
        );
    }

    /**
     * UTC bounds [start, end) covering the whole selected date range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(): array
    {
        return OpsClock::rangeBounds($this->from, $this->to);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * Query-string parameters reproducing these filters (used for links and exports).
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'definition' => $this->definitionId,
            'status' => $this->status,
            'user' => $this->userId,
            'category' => $this->categoryId,
            'basis' => $this->basis !== 'operational' ? $this->basis : null,
            'order' => $this->order !== 'asc' ? $this->order : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Human-readable list of applied filters, shown above results and in exports.
     *
     * @return array<string, string>
     */
    public function describe(): array
    {
        return array_filter([
            'Period' => $this->from->equalTo($this->to)
                ? $this->from->format('D, d M Y')
                : $this->from->format('d M Y').' – '.$this->to->format('d M Y').' ('.$this->days().' days)',
            'Activity' => $this->definitionId ? optional(ActivityDefinition::find($this->definitionId), fn ($d) => "{$d->code} · {$d->title}") : null,
            'Category' => $this->categoryId ? Category::find($this->categoryId)?->name : null,
            'Status' => $this->status ? self::STATUSES[$this->status] : null,
            'Personnel' => $this->userId ? User::find($this->userId)?->name : null,
            'Date basis' => $this->type === 'history' ? ($this->basis === 'recorded' ? 'Time the update was recorded' : 'Operational date of the activity') : null,
        ]);
    }
}
