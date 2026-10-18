<?php

namespace App\View\Composers;

use App\Enums\ActivityStatus;
use App\Models\ActivityInstance;
use App\Models\Handover;
use App\Support\OpsClock;
use Illuminate\View\View;

/**
 * Real counts for the application shell: outstanding activities today and
 * handovers waiting for someone other than their author to acknowledge.
 */
class AppShellComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $today = OpsClock::today();

        $view->with('shell', [
            'today' => $today,
            'shift' => OpsClock::currentShift(),
            'outstandingToday' => ActivityInstance::query()->onDate($today)->where('status', ActivityStatus::Pending)->count(),
            'awaitingAcknowledgement' => Handover::query()
                ->whereNull('acknowledged_at')
                ->where('outgoing_user_id', '!=', $user->id)
                ->where('operational_date', '>=', $today->subDays(2)->format('Y-m-d'))
                ->count(),
        ]);
    }
}
