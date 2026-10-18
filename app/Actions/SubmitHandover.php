<?php

namespace App\Actions;

use App\Models\Handover;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HandoverBoard;
use App\Support\OpsClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The outgoing person's handover for an operational date and shift. The
 * snapshot freezes counts and outstanding items at the moment of submission.
 */
class SubmitHandover
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HandoverBoard $board,
    ) {}

    public function handle(string $date, string $shift, string $note, User $outgoing): Handover
    {
        $day = OpsClock::date($date);

        if ($day->greaterThan(OpsClock::today())) {
            throw ValidationException::withMessages(['handoverDate' => 'You cannot submit a handover for a future date.']);
        }

        // The snapshot records the state now, so only allow the current
        // operational date or the previous one (a shift can end after midnight).
        if ($day->lessThan(OpsClock::today()->subDay())) {
            throw ValidationException::withMessages(['handoverDate' => 'Handovers can only be submitted for today or yesterday.']);
        }

        $alreadySubmitted = ValidationException::withMessages([
            'handoverShift' => 'A handover has already been submitted for this shift. Ask the incoming person to acknowledge it.',
        ]);

        if (Handover::query()->where('operational_date', $day->format('Y-m-d'))->where('shift', $shift)->exists()) {
            throw $alreadySubmitted;
        }

        return DB::transaction(function () use ($day, $shift, $note, $outgoing, $alreadySubmitted) {
            try {
                $handover = Handover::create([
                    'operational_date' => $day->format('Y-m-d'),
                    'shift' => $shift,
                    'outgoing_user_id' => $outgoing->id,
                    'outgoing_note' => trim($note),
                    'submitted_at' => now(),
                    'snapshot' => $this->board->snapshot($day),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two people submitted the same shift at the same moment.
                throw $alreadySubmitted;
            }

            $this->audit->record(
                event: 'handover.submitted',
                description: sprintf('Submitted %s handover for %s', strtolower($handover->shiftLabel()), $day->format('d M Y')),
                subject: $handover,
                subjectLabel: sprintf('HND-%s-%s', $day->format('Ymd'), strtoupper($shift)),
                properties: ['after' => ['outstanding' => $handover->snapshot['counts']['outstanding'] ?? 0]],
                actor: $outgoing,
            );

            return $handover;
        });
    }
}
