<?php

namespace App\Actions;

use App\Models\ActivityInstance;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Changes the person responsible for an instance. Earlier updates keep their
 * original updater; only the current assignment changes, and the change is
 * recorded in the audit log so the instance timeline shows the reassignment.
 */
class AssignActivityInstance
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(ActivityInstance $instance, ?User $assignee, User $actor): ActivityInstance
    {
        if ($instance->assigned_to === $assignee?->id) {
            return $instance;
        }

        return DB::transaction(function () use ($instance, $assignee, $actor) {
            $previous = $instance->assignee;

            $instance->forceFill(['assigned_to' => $assignee?->id])->save();

            $this->audit->record(
                event: 'activity.reassigned',
                description: sprintf('Responsible person changed for %s', $instance->definition->title),
                subject: $instance,
                subjectLabel: $instance->reference(),
                properties: [
                    'before' => ['assigned_to' => $previous?->name ?? 'Unassigned'],
                    'after' => ['assigned_to' => $assignee?->name ?? 'Unassigned'],
                ],
                actor: $actor,
            );

            return $instance->setRelation('assignee', $assignee);
        });
    }
}
