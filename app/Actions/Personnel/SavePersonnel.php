<?php

namespace App\Actions\Personnel;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a personnel account.
 *
 * New accounts receive an unusable random password and an emailed link to
 * set their own, so an administrator never knows or transmits a password.
 */
class SavePersonnel
{
    private const AUDITED = ['name', 'email', 'staff_number', 'role', 'designation', 'team'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Validated attributes.
     */
    public function handle(?User $user, array $data, User $actor): User
    {
        $creating = $user === null;

        if (! $creating && $user->isAdministrator() && ($data['role'] ?? null) !== Role::Administrator->value) {
            $this->guardLastAdministrator($user);
        }

        $user = DB::transaction(function () use ($user, $data, $actor, $creating) {
            $user ??= (new User)->forceFill(['password' => Str::password(40), 'is_active' => true]);
            $before = $creating ? [] : $this->auditable($user);

            $user->fill(Arr::only($data, self::AUDITED))->save();
            $after = $this->auditable($user);
            $changed = $creating ? array_keys($after) : array_keys(array_diff_assoc(array_map('strval', $before), array_map('strval', $after)));

            if ($creating || $changed !== []) {
                $this->audit->record(
                    event: match (true) {
                        $creating => 'personnel.created',
                        in_array('role', $changed, true) => 'personnel.role_changed',
                        default => 'personnel.updated',
                    },
                    description: sprintf('%s personnel account for %s', $creating ? 'Created' : 'Updated', $user->name),
                    subject: $user,
                    subjectLabel: $user->staff_number ?? $user->email,
                    properties: ['before' => Arr::only($before, $changed), 'after' => Arr::only($after, $changed)],
                    actor: $actor,
                );
            }

            return $user;
        });

        if ($creating) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return $user;
    }

    public function guardLastAdministrator(User $user, string $errorKey = 'role'): void
    {
        $otherActiveAdmins = User::query()->active()
            ->where('role', Role::Administrator)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $otherActiveAdmins) {
            throw ValidationException::withMessages([$errorKey => 'At least one active administrator is required. Appoint another administrator first.']);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function auditable(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'staff_number' => $user->staff_number,
            'role' => $user->role?->value,
            'designation' => $user->designation,
            'team' => $user->team,
        ];
    }
}
