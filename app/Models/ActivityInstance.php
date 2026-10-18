<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Models\Concerns\HasOperationalDate;
use App\Support\OpsClock;
use Carbon\CarbonInterface;
use Database\Factories\ActivityInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One activity definition on one operational date.
 *
 * Current-state columns (status, latest_update_id, last_updated_*,
 * requires_follow_up, version) are maintained exclusively by
 * App\Actions\RecordActivityUpdate inside a transaction together with the
 * appended ActivityUpdate row.
 */
#[Fillable([
    'activity_definition_id', 'operational_date', 'assigned_to', 'origin', 'created_by',
    'scheduled_at', 'due_at', 'closes_at',
])]
class ActivityInstance extends Model
{
    /** @use HasFactory<ActivityInstanceFactory> */
    use HasFactory, HasOperationalDate;

    public const ORIGIN_SCHEDULE = 'schedule';

    public const ORIGIN_MANUAL = 'manual';

    protected $attributes = [
        'status' => 'pending',
        'version' => 0,
        'requires_follow_up' => false,
        'origin' => self::ORIGIN_SCHEDULE,
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'scheduled_at' => 'datetime',
            'due_at' => 'datetime',
            'closes_at' => 'datetime',
            'last_updated_at' => 'datetime',
            'requires_follow_up' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ActivityDefinition::class, 'activity_definition_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function latestUpdate(): BelongsTo
    {
        return $this->belongsTo(ActivityUpdate::class, 'latest_update_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ActivityUpdate::class);
    }

    /**
     * @param  Builder<ActivityInstance>  $query
     */
    public function scopeOnDate(Builder $query, CarbonInterface|string $date): void
    {
        $query->where('operational_date', OpsClock::date($date)->format('Y-m-d'));
    }

    /**
     * Pending instances from earlier operational dates inside the carry-over window.
     *
     * @param  Builder<ActivityInstance>  $query
     */
    public function scopeCarriedOverInto(Builder $query, CarbonInterface $date): void
    {
        $query->where('status', ActivityStatus::Pending)
            ->where('operational_date', '<', $date->format('Y-m-d'))
            ->where('operational_date', '>=', $date->subDays(config('operations.carry_over_days'))->format('Y-m-d'));
    }

    /**
     * Eager loads needed by every list that shows an instance row.
     *
     * @param  Builder<ActivityInstance>  $query
     */
    public function scopeWithRowContext(Builder $query): void
    {
        $query->with(['definition.category', 'assignee', 'latestUpdate']);
    }

    public function isDone(): bool
    {
        return $this->status === ActivityStatus::Done;
    }

    public function hasUpdates(): bool
    {
        return $this->latest_update_id !== null;
    }

    /**
     * "not_updated" | "pending" | "done" â€” the three states the UI distinguishes.
     */
    public function displayState(): string
    {
        if (! $this->hasUpdates()) {
            return 'not_updated';
        }

        return $this->status->value;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function isCarriedOver(?CarbonInterface $today = null): bool
    {
        return ! $this->isDone()
            && $this->operational_date->format('Y-m-d') < ($today ?? OpsClock::today())->format('Y-m-d');
    }

    public function wasReopened(): bool
    {
        return $this->latestUpdate?->isReopening() ?? false;
    }

    /**
     * Stable reference for an instance, e.g. ACT-001-20261018.
     */
    public function reference(): string
    {
        return $this->definition->code.'-'.$this->operational_date->format('Ymd');
    }

    /**
     * Reasons this instance needs attention, derived only from stored state.
     *
     * @return array<int, string>
     */
    public function attentionReasons(?CarbonInterface $today = null): array
    {
        if ($this->isDone()) {
            return [];
        }

        return array_values(array_filter([
            $this->definition->priority === Priority::Critical ? 'Critical priority' : null,
            $this->requires_follow_up ? 'Flagged for follow-up' : null,
            $this->wasReopened() ? 'Reopened' : null,
            $this->isCarriedOver($today) ? 'Carried over from '.$this->operational_date->format('d M') : null,
            $this->isOverdue() && ! $this->isCarriedOver($today) ? 'Past due '.ops_time($this->due_at) : null,
        ]));
    }
}
