<?php

namespace App\Actions;

use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Creates the activity instances that are due on an operational date.
 *
 * Idempotent: an instance is identified by (definition, operational date),
 * which is unique in the database. Running this any number of times for the
 * same date (scheduler retries, the lazy call made when someone opens today's
 * register, a manual backfill) creates each instance at most once and never
 * touches instances that already exist or their history.
 */
class GenerateDailyInstances
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return int The number of instances created by this call.
     */
    public function handle(DateTimeInterface|string $date, ?ActivityDefinition $only = null): int
    {
        $date = OpsClock::date($date);

        $definitions = ($only ? collect([$only]) : ActivityDefinition::query()->active()->with('defaultAssignee')->get())
            ->filter(fn (ActivityDefinition $definition) => $definition->appliesOn($date));

        if ($definitions->isEmpty()) {
            return 0;
        }

        $existing = ActivityInstance::query()
            ->onDate($date)
            ->whereIn('activity_definition_id', $definitions->pluck('id')->all())
            ->pluck('activity_definition_id')
            ->all();

        $created = $definitions
            ->reject(fn (ActivityDefinition $definition) => in_array($definition->id, $existing, true))
            ->map(fn (ActivityDefinition $definition) => ActivityInstance::createOrFirst(
                ['activity_definition_id' => $definition->id, 'operational_date' => $date->format('Y-m-d')],
                $this->attributesFor($definition, $date),
            ))
            ->filter(fn (ActivityInstance $instance) => $instance->wasRecentlyCreated)
            ->count();

        if ($created > 0) {
            $this->audit->recordSystem(
                event: 'activity.instances_generated',
                description: sprintf('Generated %d activity instance%s for %s', $created, $created === 1 ? '' : 's', $date->format('d M Y')),
                properties: ['operational_date' => $date->format('Y-m-d'), 'created' => $created],
            );
        }

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesFor(ActivityDefinition $definition, CarbonImmutable $date): array
    {
        return [
            'assigned_to' => $definition->defaultAssignee?->is_active ? $definition->default_assignee_id : null,
            'origin' => ActivityInstance::ORIGIN_SCHEDULE,
            'scheduled_at' => $definition->scheduled_time ? OpsClock::at($date, $definition->scheduled_time) : null,
            'due_at' => $definition->due_time ? OpsClock::at($date, $definition->due_time) : null,
            'closes_at' => OpsClock::dayBounds($date)[1],
        ];
    }
}
