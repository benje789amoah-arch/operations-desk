<?php

namespace App\Actions;

use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manually schedules a definition for a specific operational date: used for
 * ad-hoc activities and for one-off extra runs of a recurring check.
 */
class ScheduleActivityInstance
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(ActivityDefinition $definition, string $date, ?User $assignee, User $actor): ActivityInstance
    {
        $day = OpsClock::date($date);

        if ($definition->isArchived()) {
            throw ValidationException::withMessages(['scheduleDefinition' => 'Archived definitions cannot be scheduled. Restore it first.']);
        }

        // Adding work to a past day would fabricate history.
        if ($day->lessThan(OpsClock::today())) {
            throw ValidationException::withMessages(['scheduleDefinition' => 'Activities can only be scheduled for today or a future date.']);
        }

        $alreadyScheduled = ValidationException::withMessages([
            'scheduleDefinition' => sprintf('%s is already on the register for %s.', $definition->code, $day->format('d M Y')),
        ]);

        if (ActivityInstance::query()->where('activity_definition_id', $definition->id)->onDate($day)->exists()) {
            throw $alreadyScheduled;
        }

        return DB::transaction(function () use ($definition, $day, $assignee, $actor, $alreadyScheduled) {
            $attributes = GenerateDailyInstances::attributesFor($definition, $day);

            try {
                $instance = ActivityInstance::create([
                    ...$attributes,
                    'activity_definition_id' => $definition->id,
                    'operational_date' => $day->format('Y-m-d'),
                    // The default owner is only used while their account is active.
                    'assigned_to' => $assignee?->id ?? $attributes['assigned_to'],
                    'origin' => ActivityInstance::ORIGIN_MANUAL,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Someone scheduled it (or the scheduler created it) a moment ago.
                throw $alreadyScheduled;
            }

            $this->audit->record(
                event: 'activity.instance_scheduled',
                description: sprintf('Scheduled %s for %s', $definition->title, $day->format('d M Y')),
                subject: $instance,
                subjectLabel: $instance->reference(),
                properties: ['after' => ['operational_date' => $day->format('Y-m-d'), 'assigned_to' => $instance->assignee?->name ?? 'Unassigned']],
                actor: $actor,
            );

            return $instance;
        });
    }
}
