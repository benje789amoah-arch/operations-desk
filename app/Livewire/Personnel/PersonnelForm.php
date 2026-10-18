<?php

namespace App\Livewire\Personnel;

use App\Actions\Personnel\SavePersonnel;
use App\Actions\Personnel\SetPersonnelActive;
use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create or manage a personnel account (administrators only).
 */
class PersonnelForm extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $staff_number = '';

    public string $role = 'support';

    public string $designation = '';

    public string $team = 'Applications Support';

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->authorize('update', $user);
            $this->userId = $user->id;
            $this->fill($user->only(['name', 'email', 'designation', 'team']));
            $this->staff_number = (string) $user->staff_number;
            $this->role = $user->role->value;
            $this->designation = (string) $user->designation;
            $this->team = (string) $user->team;
        } else {
            $this->authorize('create', User::class);
        }
    }

    public function person(): ?User
    {
        return $this->userId ? User::findOrFail($this->userId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'staff_number' => ['nullable', 'string', 'max:32', Rule::unique('users', 'staff_number')->ignore($this->userId)],
            'role' => ['required', Rule::enum(Role::class)],
            'designation' => ['nullable', 'string', 'max:120'],
            'team' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function save(SavePersonnel $action)
    {
        $person = $this->person();
        $person ? $this->authorize('update', $person) : $this->authorize('create', User::class);

        // Stored emails are lower case (see User::email()), so the unique rule must compare lower case too.
        $this->email = Str::lower(trim($this->email));
        // Livewire skips TrimStrings, so trim the free-text fields here.
        foreach (['name', 'staff_number', 'designation', 'team'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        $data = $this->validate();
        $data['staff_number'] = $data['staff_number'] ?: null;
        $data['designation'] = $data['designation'] ?: null;
        $data['team'] = $data['team'] ?: null;

        $saved = $action->handle($person, $data, auth()->user());

        if (! $person) {
            session()->flash('toast', "Account created for {$saved->name}. A link to set their password has been emailed to {$saved->email}.");

            return $this->redirectRoute('personnel.edit', $saved);
        }

        $this->dispatch('toast', message: 'Personnel details saved.');
    }

    public function setActive(bool $active, SetPersonnelActive $action): void
    {
        $person = $this->person();
        $this->authorize('changeStatus', $person);

        $action->handle($person, $active, auth()->user());

        $this->dispatch('toast', message: $active ? "{$person->name} can sign in again." : "{$person->name} has been deactivated and signed out. Their records are kept.", tone: $active ? 'positive' : 'attention');
    }

    public function sendResetLink(AuditLogger $audit): void
    {
        $person = $this->person();
        $this->authorize('update', $person);

        if (! $person->is_active) {
            $this->dispatch('toast', message: 'Reactivate the account before sending a reset link.', tone: 'attention');

            return;
        }

        Password::broker()->sendResetLink(['email' => $person->email]);
        $audit->record('personnel.password_reset_sent', "Sent a password reset link to {$person->name}", $person, $person->staff_number ?? $person->email);

        $this->dispatch('toast', message: "Password reset link sent to {$person->email}.");
    }

    public function render()
    {
        $person = $this->person();

        return view('livewire.personnel.personnel-form', [
            'person' => $person,
            'roles' => Role::cases(),
            'recentUpdates' => $person ? $person->activityUpdates()->with('instance.definition')->latest('id')->limit(8)->get() : collect(),
            'recentEvents' => $person ? AuditEvent::query()->where('actor_id', $person->id)->latest('id')->limit(8)->get() : collect(),
            'accountEvents' => $person ? AuditEvent::query()->where('subject_type', 'User')->where('subject_id', $person->id)->latest('id')->limit(6)->get() : collect(),
        ])->title($person ? $person->name : 'Add personnel');
    }
}
