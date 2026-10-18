<?php

namespace App\Actions;

use App\Models\Handover;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The incoming person confirms they have read the handover. Only someone
 * other than the outgoing person can acknowledge it, and only once.
 */
class AcknowledgeHandover
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Handover $handover, User $incoming, ?string $note): Handover
    {
        return DB::transaction(function () use ($handover, $incoming, $note) {
            $handover = Handover::query()->lockForUpdate()->findOrFail($handover->id);

            if ($handover->isAcknowledged()) {
                throw ValidationException::withMessages(['acknowledgement' => 'This handover has already been acknowledged.']);
            }

            if ($handover->outgoing_user_id === $incoming->id) {
                throw ValidationException::withMessages(['acknowledgement' => 'The incoming person must acknowledge the handover, not the person who submitted it.']);
            }

            $handover->forceFill([
                'incoming_user_id' => $incoming->id,
                'acknowledged_at' => now(),
                'acknowledgement_note' => filled($note) ? trim($note) : null,
            ])->save();

            $this->audit->record(
                event: 'handover.acknowledged',
                description: sprintf('Acknowledged %s handover for %s', strtolower($handover->shiftLabel()), $handover->operational_date->format('d M Y')),
                subject: $handover,
                subjectLabel: sprintf('HND-%s-%s', $handover->operational_date->format('Ymd'), strtoupper($handover->shift)),
                actor: $incoming,
            );

            return $handover;
        });
    }
}
