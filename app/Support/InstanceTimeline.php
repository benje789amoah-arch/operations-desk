<?php

namespace App\Support;

use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Models\AuditEvent;
use Illuminate\Support\Collection;

/**
 * The full history of one activity instance, oldest first: when it was put
 * on the register, every status update (with the updater's details as they
 * were at the time) and every reassignment. Built from stored records only.
 */
class InstanceTimeline
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function for(ActivityInstance $instance): Collection
    {
        $instance->loadMissing(['creator', 'updates']);

        $created = [[
            'type' => 'created',
            'at' => $instance->created_at,
            'sort' => 0,
            'actor' => $instance->origin === ActivityInstance::ORIGIN_MANUAL ? ($instance->creator?->name ?? 'Unknown') : 'Scheduler',
            'actor_meta' => $instance->origin === ActivityInstance::ORIGIN_MANUAL ? 'Scheduled manually' : 'Generated from the recurrence rule',
            'label' => 'Added to the register',
            'context' => 'Operational date '.$instance->operational_date->format('D, d M Y'),
        ]];

        $updates = $instance->updates->map(fn (ActivityUpdate $update) => [
            'type' => 'update',
            'at' => $update->created_at,
            'sort' => $update->id,
            'id' => $update->id,
            'actor' => $update->updater_name,
            'actor_meta' => collect([$update->updater_designation, $update->updater_staff_number, $update->updater_role->label()])->filter()->join(' · '),
            'label' => $update->eventLabel(),
            'previous_status' => $update->previous_status?->value ?? 'not_updated',
            'new_status' => $update->isReopening() ? 'reopened' : $update->new_status->value,
            'remark' => $update->remark,
            'supporting_details' => $update->supporting_details,
            'follow_up' => $update->requires_follow_up,
        ]);

        $events = AuditEvent::query()
            ->where('subject_type', class_basename(ActivityInstance::class))
            ->where('subject_id', $instance->id)
            ->whereIn('event', ['activity.reassigned', 'activity.instance_scheduled'])
            ->orderBy('id')
            ->get()
            ->map(fn (AuditEvent $event) => [
                'type' => $event->event === 'activity.reassigned' ? 'reassigned' : 'scheduled',
                'at' => $event->created_at,
                'sort' => PHP_INT_MAX - 1,
                'actor' => $event->actor_name,
                'actor_meta' => null,
                'label' => $event->event === 'activity.reassigned' ? 'Responsible person changed' : 'Scheduled for this date',
                'context' => $event->event === 'activity.reassigned'
                    ? ($event->properties['before']['assigned_to'] ?? 'Unassigned').' → '.($event->properties['after']['assigned_to'] ?? 'Unassigned')
                    : 'Assigned to '.($event->properties['after']['assigned_to'] ?? 'Unassigned'),
            ]);

        return collect($created)
            ->concat($updates)
            ->concat($events)
            ->sortBy([['at', 'asc'], ['sort', 'asc']])
            ->values();
    }
}
