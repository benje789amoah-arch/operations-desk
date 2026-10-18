<?php

namespace App\Actions;

use App\Enums\ActivityStatus;
use App\Exceptions\StaleActivityUpdateException;
use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a Done/Pending update against an activity instance.
 *
 * Guarantees:
 *  - The update is attributed to $user (the authenticated account); callers
 *    never pass an updater identity from request input.
 *  - created_at is set by the server at insert time.
 *  - The appended update row, the instance's current state and the audit
 *    event are written in one transaction: all three persist or none do.
 *  - Optimistic locking: $expectedVersion must match the instance's current
 *    version, otherwise StaleActivityUpdateException is thrown and nothing
 *    is written. This stops two people silently overwriting each other and
 *    also rejects accidental double submissions.
 */
class RecordActivityUpdate
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        ActivityInstance $instance,
        User $user,
        ActivityStatus $status,
        string $remark,
        int $expectedVersion,
        ?string $supportingDetails = null,
        bool $requiresFollowUp = false,
    ): ActivityUpdate {
        $remark = trim($remark);

        if ($remark === '') {
            throw new InvalidArgumentException('A remark is required for every update.');
        }

        return DB::transaction(function () use ($instance, $user, $status, $remark, $expectedVersion, $supportingDetails, $requiresFollowUp) {
            $current = ActivityInstance::query()->with('definition')->lockForUpdate()->findOrFail($instance->getKey());

            if ($current->version !== $expectedVersion) {
                throw new StaleActivityUpdateException($current->load('latestUpdate'));
            }

            // A follow-up flag only makes sense while work is outstanding.
            $followUp = $status === ActivityStatus::Pending && $requiresFollowUp;

            $update = $current->updates()->create([
                'user_id' => $user->id,
                ...$user->bioSnapshot(),
                'previous_status' => $current->hasUpdates() ? $current->status : null,
                'new_status' => $status,
                'remark' => $remark,
                'supporting_details' => filled($supportingDetails) ? trim($supportingDetails) : null,
                'requires_follow_up' => $followUp,
            ]);

            // The version condition is the real concurrency guard: it holds
            // even on drivers where lockForUpdate() is a no-op (SQLite).
            $affected = ActivityInstance::query()
                ->whereKey($current->getKey())
                ->where('version', $expectedVersion)
                ->update([
                    'status' => $status->value,
                    'latest_update_id' => $update->id,
                    'last_updated_at' => $update->created_at,
                    'last_updated_by' => $user->id,
                    'requires_follow_up' => $followUp,
                    'version' => $expectedVersion + 1,
                    'updated_at' => $update->created_at,
                ]);

            if ($affected !== 1) {
                throw new StaleActivityUpdateException($current->fresh('latestUpdate'));
            }

            $this->audit->record(
                event: match (true) {
                    $update->isReopening() => 'activity.reopened',
                    $status === ActivityStatus::Done => 'activity.completed',
                    default => 'activity.status_updated',
                },
                description: sprintf('%s: %s', $update->eventLabel(), $current->definition->title),
                subject: $current,
                subjectLabel: $current->reference(),
                properties: [
                    'before' => ['status' => $current->hasUpdates() ? $current->status->value : 'not updated'],
                    'after' => ['status' => $status->value, 'follow_up' => $followUp],
                    'update_id' => $update->id,
                ],
                actor: $user,
            );

            return $update;
        });
    }
}
