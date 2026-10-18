<?php

namespace App\Http\Controllers;

use App\Models\Handover;
use App\Models\User;
use App\Services\HandoverBoard;
use App\Support\OpsClock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paper-friendly handover summary: no navigation or controls, only the
 * period, counts, outstanding items with their latest remarks, completed
 * work, the update journal and any submitted handovers for the date.
 */
class HandoverPrintController extends Controller
{
    public function __invoke(Request $request, HandoverBoard $board): View
    {
        $input = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shift' => ['nullable', Rule::in(array_keys(config('operations.shifts')))],
            'person' => ['nullable', 'integer'],
        ]);

        $date = OpsClock::safeDate($input['date'] ?? null);
        $personId = isset($input['person']) ? (int) $input['person'] : null;
        $shift = $input['shift'] ?? null;

        return view('handover.print', [
            'date' => $date,
            'shift' => $shift,
            'person' => $personId ? User::find($personId) : null,
            'counts' => $board->counts($date),
            'outstanding' => $board->outstanding($date, $personId),
            'completed' => $board->completed($date, $personId),
            'journal' => $board->journal($date, $shift, $personId),
            'handovers' => Handover::query()->where('operational_date', $date->format('Y-m-d'))->with(['outgoingUser', 'incomingUser'])->orderBy('submitted_at')->get(),
            'preparedBy' => $request->user(),
        ]);
    }
}
