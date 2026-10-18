<?php

namespace App\Livewire;

use App\Actions\GenerateDailyInstances;
use App\Models\ActivityInstance;
use App\Services\DeskOverview;
use App\Support\OpsClock;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Overview of today's operational position. Every number is a database
 * query; see App\Services\DeskOverview for the metric definitions.
 */
#[Title('Overview')]
class Dashboard extends Component
{
    #[Url(except: 'all')]
    public string $tab = 'all';

    public function mount(GenerateDailyInstances $generator): void
    {
        $generator->handle(OpsClock::today());
    }

    public function greeting(): string
    {
        $hour = OpsClock::now()->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    public function render(DeskOverview $overview)
    {
        $today = OpsClock::today();
        $activities = $overview->todaysActivities($today);

        return view('livewire.dashboard', [
            'today' => $today,
            'metrics' => $overview->metrics($today),
            'activities' => $activities->filter(fn (ActivityInstance $i) => match ($this->tab) {
                'pending' => $i->hasUpdates() && ! $i->isDone(),
                'not_updated' => ! $i->hasUpdates(),
                'done' => $i->isDone(),
                default => true,
            }),
            'tabCounts' => [
                'all' => $activities->count(),
                'not_updated' => $activities->filter(fn ($i) => ! $i->hasUpdates())->count(),
                'pending' => $activities->filter(fn ($i) => $i->hasUpdates() && ! $i->isDone())->count(),
                'done' => $activities->filter->isDone()->count(),
            ],
            'attention' => $overview->requiringAttention($today),
            'recent' => $overview->recentUpdates(8),
            'trend' => $overview->trend($today, 14),
            'shift' => OpsClock::currentShift(),
        ]);
    }
}
