<?php

namespace App\Livewire\Reports;

use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Support\Ability;
use App\Support\OpsClock;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reporting centre')]
class ReportIndex extends Component
{
    public function render()
    {
        $user = auth()->user();
        $today = OpsClock::today();

        return view('livewire.reports.report-index', [
            'reports' => collect(ReportRegistry::all())->map(fn (string $class) => app($class))
                ->map(fn (Report $report) => [
                    'report' => $report,
                    'available' => $report->availableTo($user),
                    'icon' => ['daily' => 'calendar-check', 'history' => 'history', 'pending' => 'circle-dot', 'personnel' => 'users', 'trend' => 'chart-column'][$report->key()],
                ]),
            'canExport' => $user->can(Ability::EXPORT_REPORTS),
            'presets' => [
                'Today' => [$today, $today],
                'Yesterday' => [$today->subDay(), $today->subDay()],
                'Last 7 days' => [$today->subDays(6), $today],
                'Month to date' => [$today->startOfMonth(), $today],
                'Last 30 days' => [$today->subDays(29), $today],
            ],
        ]);
    }
}
