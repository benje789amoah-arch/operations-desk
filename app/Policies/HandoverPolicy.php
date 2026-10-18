<?php

namespace App\Policies;

use App\Models\Handover;
use App\Models\User;
use App\Support\Ability;
use Illuminate\Auth\Access\Response;

class HandoverPolicy
{
    public function acknowledge(User $user, Handover $handover): Response
    {
        return match (true) {
            $user->cannot(Ability::SUBMIT_HANDOVER) => Response::deny('Your role cannot acknowledge handovers.'),
            $handover->isAcknowledged() => Response::deny('This handover has already been acknowledged.'),
            $handover->outgoing_user_id === $user->id => Response::deny('The incoming person must acknowledge the handover, not the person who submitted it.'),
            default => Response::allow(),
        };
    }
}
