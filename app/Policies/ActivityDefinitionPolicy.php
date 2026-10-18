<?php

namespace App\Policies;

use App\Models\ActivityDefinition;
use App\Models\User;
use App\Support\Ability;

class ActivityDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ActivityDefinition $definition): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::MANAGE_DEFINITIONS);
    }

    public function update(User $user, ActivityDefinition $definition): bool
    {
        return $user->can(Ability::MANAGE_DEFINITIONS);
    }

    public function archive(User $user, ActivityDefinition $definition): bool
    {
        return $user->can(Ability::MANAGE_DEFINITIONS);
    }

    public function schedule(User $user, ActivityDefinition $definition): bool
    {
        return $user->can(Ability::SCHEDULE_ACTIVITIES) && ! $definition->isArchived();
    }
}
