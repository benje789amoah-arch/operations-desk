<?php

namespace App\Models;

use App\Models\Concerns\HasOperationalDate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shift handover: submitted by the outgoing person, acknowledged by a
 * different incoming person. Both steps are persisted and audited.
 */
#[Fillable([
    'operational_date', 'shift', 'outgoing_user_id', 'outgoing_note', 'submitted_at', 'snapshot',
])]
class Handover extends Model
{
    use HasOperationalDate;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function outgoingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outgoing_user_id');
    }

    public function incomingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'incoming_user_id');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function shiftLabel(): string
    {
        return config("operations.shifts.{$this->shift}.label", ucfirst($this->shift));
    }
}
