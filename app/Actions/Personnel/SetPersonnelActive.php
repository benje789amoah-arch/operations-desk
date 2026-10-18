<?php

namespace App\Actions\Personnel;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Activates or deactivates an account. Deactivated users cannot sign in, any
 * active sessions are ended, and every record they authored is preserved.
 */
class SetPersonnelActive
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SavePersonnel $savePersonnel,
    ) {}

    public function handle(User $user, bool $active, User $actor): User
    {
        if ($user->is_active === $active) {
            return $user;
        }

        if (! $active && $user->is($actor)) {
            throw ValidationException::withMessages(['status' => 'You cannot deactivate your own account.']);
        }

        if (! $active && $user->isAdministrator()) {
            $this->savePersonnel->guardLastAdministrator($user, 'status');
        }

        return DB::transaction(function () use ($user, $active, $actor) {
            $user->forceFill([
                'is_active' => $active,
                'deactivated_at' => $active ? null : now(),
                'remember_token' => null,
            ])->save();

            if (! $active && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->audit->record(
                event: $active ? 'personnel.activated' : 'personnel.deactivated',
                description: sprintf('%s account for %s', $active ? 'Reactivated' : 'Deactivated', $user->name),
                subject: $user,
                subjectLabel: $user->staff_number ?? $user->email,
                properties: ['before' => ['active' => ! $active], 'after' => ['active' => $active]],
                actor: $actor,
            );

            return $user;
        });
    }
}
