<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * A system audit record. Written only by App\Services\AuditLogger and never
 * modified afterwards (see the hash chain described in the migration).
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only and cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only and cannot be deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The event family, e.g. "activity" for "activity.status_updated".
     */
    public function category(): string
    {
        return Str::before($this->event, '.');
    }

    /**
     * Visual tone used by the audit log: neutral, positive, attention or critical.
     */
    public function tone(): string
    {
        return match (true) {
            Str::contains($this->event, ['failed', 'locked', 'deactivated']) => 'critical',
            Str::contains($this->event, ['reopened', 'archived', 'role_changed', 'password_reset']) => 'attention',
            Str::contains($this->event, ['created', 'completed', 'acknowledged', 'activated', 'restored']) => 'positive',
            default => 'neutral',
        };
    }

    /**
     * Before/after pairs for the diff inspector.
     *
     * @return array<int, array{field: string, before: mixed, after: mixed}>
     */
    public function changes(): array
    {
        $before = $this->properties['before'] ?? [];
        $after = $this->properties['after'] ?? [];

        return collect(array_unique(array_merge(array_keys($before), array_keys($after))))
            ->map(fn (string $field) => ['field' => $field, 'before' => $before[$field] ?? null, 'after' => $after[$field] ?? null])
            ->values()
            ->all();
    }
}
