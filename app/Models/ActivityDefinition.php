<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\Recurrence;
use Carbon\CarbonInterface;
use Database\Factories\ActivityDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring (or ad-hoc) support check. Daily work happens on
 * ActivityInstance rows generated from a definition; editing a definition
 * never rewrites instances or updates that already exist.
 */
#[Fillable([
    'code', 'title', 'description', 'category_id', 'priority', 'recurrence', 'recurrence_days',
    'scheduled_time', 'due_time', 'default_assignee_id', 'starts_on', 'ends_on', 'created_by', 'updated_by',
])]
class ActivityDefinition extends Model
{
    /** @use HasFactory<ActivityDefinitionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'recurrence' => Recurrence::class,
            'recurrence_days' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ActivityInstance::class);
    }

    /**
     * @param  Builder<ActivityDefinition>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<ActivityDefinition>  $query
     */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Whether this definition should produce an instance on the given local date.
     */
    public function appliesOn(CarbonInterface $date): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        // Compare calendar dates as strings: starts_on/ends_on are timezone-less
        // dates, while $date is a local operational date.
        $day = $date->format('Y-m-d');

        if ($day < $this->starts_on->format('Y-m-d') || ($this->ends_on && $day > $this->ends_on->format('Y-m-d'))) {
            return false;
        }

        return $this->recurrence->appliesOn($date, $this->recurrence_days ?? []);
    }

    public function scheduleLabel(): string
    {
        $label = $this->recurrence->describe($this->recurrence_days ?? []);

        if ($this->scheduled_time) {
            $label .= ' · '.substr($this->scheduled_time, 0, 5);
        }

        return $label;
    }

    /**
     * Next human-readable code, e.g. ACT-007. The unique index on "code" is the
     * final guard if two administrators create definitions at the same moment.
     */
    public static function nextCode(): string
    {
        $highest = static::query()->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->max() ?? 0;

        return sprintf('ACT-%03d', $highest + 1);
    }
}
