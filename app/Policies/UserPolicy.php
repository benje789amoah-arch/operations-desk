<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Ability;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::MANAGE_PERSONNEL);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Ability::MANAGE_PERSONNEL);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::MANAGE_PERSONNEL);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Ability::MANAGE_PERSONNEL);
    }

    public function changeStatus(User $user, User $model): bool
    {
        return $user->can(Ability::MANAGE_PERSONNEL) && ! $user->is($model);
    }
}
