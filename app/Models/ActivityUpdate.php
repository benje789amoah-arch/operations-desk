<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An append-only status update. Rows are created by RecordActivityUpdate and
 * can never be changed or deleted through the application.
 */
#[Fillable([
    'activity_instance_id', 'user_id', 'updater_name', 'updater_email', 'updater_staff_number',
    'updater_designation', 'updater_role', 'previous_status', 'new_status', 'remark',
    'supporting_details', 'requires_follow_up',
])]
class ActivityUpdate extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'previous_status' => ActivityStatus::class,
            'new_status' => ActivityStatus::class,
            'updater_role' => Role::class,
            'requires_follow_up' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity updates are append-only and cannot be modified.'));
        static::deleting(fn () => throw new LogicException('Activity updates are append-only and cannot be deleted.'));
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ActivityInstance::class, 'activity_instance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReopening(): bool
    {
        return $this->previous_status === ActivityStatus::Done && $this->new_status === ActivityStatus::Pending;
    }

    public function isFirstUpdate(): bool
    {
        return $this->previous_status === null;
    }

    /**
     * Short description of what this update did, for timelines and reports.
     */
    public function eventLabel(): string
    {
        return match (true) {
            $this->isReopening() => 'Reopened',
            $this->new_status === ActivityStatus::Done && $this->previous_status !== ActivityStatus::Done => 'Marked done',
            $this->new_status === ActivityStatus::Done => 'Note added (done)',
            $this->isFirstUpdate() => 'Marked pending',
            default => 'Progress note (pending)',
        };
    }
}
