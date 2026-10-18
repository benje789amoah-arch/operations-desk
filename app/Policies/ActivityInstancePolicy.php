<?php

namespace App\Policies;

use App\Models\ActivityInstance;
use App\Models\User;
use App\Support\Ability;
use App\Support\OpsClock;
use Illuminate\Auth\Access\Response;

class ActivityInstancePolicy
{
    public function view(User $user, ActivityInstance $instance): bool
    {
        return $user->is_active;
    }

    /**
     * Updates open on the activity's operational date: a check scheduled for
     * tomorrow cannot be marked done today.
     */
    public function recordUpdate(User $user, ActivityInstance $instance): Response
    {
        if ($user->cannot(Ability::RECORD_UPDATES)) {
            return Response::deny('Your role cannot record activity updates.');
        }

        if ($instance->operational_date->format('Y-m-d') > OpsClock::today()->format('Y-m-d')) {
            return Response::deny('This activity is scheduled for '.$instance->operational_date->format('d M Y').'. Updates open on that day.');
        }

        return Response::allow();
    }

    public function assign(User $user, ActivityInstance $instance): bool
    {
        return $user->can(Ability::ASSIGN_ACTIVITIES);
    }
}
