<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\SetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'staff_number', 'role', 'designation', 'team', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Stored in lower case so sign-in, password resets and the unique rule
     * ignore case on every database (MySQL compares case-insensitively,
     * PostgreSQL and SQLite do not).
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => Str::lower(trim($value)));
    }

    /**
     * Used for new-account invitations and forgotten passwords alike.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new SetPasswordNotification($token));
    }

    public function activityUpdates(): HasMany
    {
        return $this->hasMany(ActivityUpdate::class);
    }

    public function assignedInstances(): HasMany
    {
        return $this->hasMany(ActivityInstance::class, 'assigned_to');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'actor_id');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdministrator(): bool
    {
        return $this->role === Role::Administrator;
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->join('');
    }

    public function firstName(): string
    {
        return Str::before($this->name, ' ');
    }

    /**
     * Point-in-time personnel details stored with every activity update.
     *
     * @return array<string, string|null>
     */
    public function bioSnapshot(): array
    {
        return [
            'updater_name' => $this->name,
            'updater_email' => $this->email,
            'updater_staff_number' => $this->staff_number,
            'updater_designation' => $this->designation,
            'updater_role' => $this->role->value,
        ];
    }
}
