<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\UsesDeskPagination;
use App\Models\ActivityDefinition;
use App\Models\Category;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Support\Ability;
use App\Support\OpsClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Runs one report type with custom-duration filters. Filters live in the URL
 * so a report can be bookmarked or shared, and every filter is applied in
 * the database query (see App\Reports).
 */
class ReportCentre extends Component
{
    use UsesDeskPagination;

    #[Locked]
    public string $type = 'daily';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $definition = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'operational')]
    public string $basis = 'operational';

    #[Url(except: 'asc')]
    public string $order = 'asc';

    public function mount(string $type): void
    {
        $this->type = $type;
        abort_unless($this->report()->availableTo(auth()->user()), 403, 'This report is available to supervisors and administrators.');

        $today = OpsClock::today();
        $this->from = $this->from ?: ($type === 'trend' ? $today->subDays(13) : $today)->format('Y-m-d');
        $this->to = $this->to ?: $today->format('Y-m-d');

        if ($type === 'personnel' && auth()->user()->cannot(Ability::VIEW_TEAM_ACTIVITY)) {
            $this->user = (string) auth()->id();
        }
    }

    public function report(): Report
    {
        return ReportRegistry::make($this->type);
    }

    public function run(): void
    {
        $this->resetPage();
    }

    public function applyPreset(string $preset): void
    {
        $today = OpsClock::today();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today],
            'last-month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            default => [$today->subDays(29), $today],
        };

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['definition', 'status', 'category', 'basis', 'order']);

        if (auth()->user()->can(Ability::VIEW_TEAM_ACTIVITY) || $this->type !== 'personnel') {
            $this->reset('user');
        }

        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'type' => $this->type,
            'from' => $this->from,
            'to' => $this->to,
            'definition' => $this->definition ?: null,
            'status' => $this->status ?: null,
            'user' => $this->user ?: null,
            'category' => $this->category ?: null,
            'basis' => $this->basis ?: null,
            'order' => $this->order ?: null,
        ];
    }

    public function render()
    {
        $report = $this->report();
        $viewer = auth()->user();
        $validator = Validator::make($this->input(), ReportFilters::rules(), ReportFilters::messages());

        $filters = null;
        $results = null;
        $summary = [];

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
        } else {
            $this->resetErrorBag();
            $filters = ReportFilters::fromValidated($validator->validated(), $viewer);
            $summary = $report->summary($filters);
            $results = $report->results($filters);

            if ($results instanceof Builder) {
                $results = $results->paginate(25);
                $report->decorate($results->getCollection(), $filters);
            }
        }

        return view('livewire.reports.report-centre', [
            'report' => $report,
            'filters' => $filters,
            'summary' => $summary,
            'results' => $results,
            'reports' => collect(ReportRegistry::all())->map(fn ($class) => app($class))->filter(fn (Report $r) => $r->availableTo($viewer)),
            'definitions' => ActivityDefinition::query()->orderBy('code')->get(['id', 'code', 'title']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'people' => $viewer->can(Ability::VIEW_TEAM_ACTIVITY) || $this->type !== 'personnel'
                ? User::query()->orderBy('name')->get(['id', 'name', 'is_active'])
                : User::query()->whereKey($viewer->id)->get(['id', 'name', 'is_active']),
            'canExport' => $viewer->can(Ability::EXPORT_REPORTS),
            'statusOptions' => match ($this->type) {
                'history', 'personnel' => ['done' => 'Done', 'pending' => 'Pending'],
                'pending' => ['pending' => 'Pending after an update', 'not_updated' => 'Not updated'],
                default => ReportFilters::STATUSES,
            },
        ])->title($report->title());
    }
}
