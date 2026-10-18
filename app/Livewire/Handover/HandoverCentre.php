<?php

namespace App\Livewire\Handover;

use App\Actions\AcknowledgeHandover;
use App\Actions\GenerateDailyInstances;
use App\Actions\SubmitHandover;
use App\Models\Handover;
use App\Models\User;
use App\Services\HandoverBoard;
use App\Support\Ability;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Everything an incoming person needs: what is outstanding (with the latest
 * explanation and earlier context), what was completed, the update journal,
 * and a persisted submit/acknowledge workflow for the shift handover.
 */
#[Title('Handover centre')]
class HandoverCentre extends Component
{
    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $shift = '';

    #[Url(except: '')]
    public string $person = '';

    public string $handoverShift = '';

    public string $handoverNote = '';

    /** @var array<int, string> */
    public array $acknowledgementNotes = [];

    public function mount(): void
    {
        $this->handoverShift = OpsClock::currentShift()['key'] ?? array_key_first(config('operations.shifts'));

        if (! array_key_exists($this->shift, config('operations.shifts'))) {
            $this->shift = '';
        }

        $this->showDate(OpsClock::safeDate($this->date ?: null));
    }

    public function day(): CarbonImmutable
    {
        return OpsClock::safeDate($this->date);
    }

    public function updatedDate(): void
    {
        $this->showDate($this->day());
    }

    public function updatedShift(): void
    {
        if (! array_key_exists($this->shift, config('operations.shifts'))) {
            $this->shift = '';
        }
    }

    public function shiftDay(int $days): void
    {
        $this->showDate($this->day()->addDays($days));
    }

    public function goToToday(): void
    {
        $this->showDate(OpsClock::today());
    }

    /**
     * Today's instances are generated on demand (idempotently) so the
     * handover is complete even if the scheduler has not run yet.
     */
    private function showDate(CarbonImmutable $date): void
    {
        $this->date = $date->format('Y-m-d');

        if ($date->isSameDay(OpsClock::today())) {
            app(GenerateDailyInstances::class)->handle($date);
        }
    }

    #[On('activity-updated')]
    public function refresh(): void {}

    public function submit(SubmitHandover $action): void
    {
        $this->authorize(Ability::SUBMIT_HANDOVER);

        $this->validate([
            'handoverShift' => ['required', Rule::in(array_keys(config('operations.shifts')))],
            'handoverNote' => ['required', 'string', 'min:15', 'max:3000'],
        ], [
            'handoverNote.required' => 'Write a short note for the incoming person: what they must watch, chase or finish.',
            'handoverNote.min' => 'Add a little more detail (at least :min characters) so the note is useful.',
        ]);

        $action->handle($this->date, $this->handoverShift, $this->handoverNote, auth()->user());

        $this->reset('handoverNote');
        $this->dispatch('toast', message: 'Handover submitted. The incoming person can now acknowledge it.');
    }

    public function acknowledge(int $handoverId, AcknowledgeHandover $action): void
    {
        $handover = Handover::findOrFail($handoverId);
        $this->authorize('acknowledge', $handover);

        $this->validate(["acknowledgementNotes.{$handoverId}" => ['nullable', 'string', 'max:1000']]);

        $action->handle($handover, auth()->user(), $this->acknowledgementNotes[$handoverId] ?? null);

        unset($this->acknowledgementNotes[$handoverId]);
        $this->dispatch('toast', message: 'Handover acknowledged. You are now responsible for the outstanding items.');
    }

    public function render(HandoverBoard $board)
    {
        $day = $this->day();
        $personId = $this->person !== '' ? (int) $this->person : null;

        return view('livewire.handover.handover-centre', [
            'day' => $day,
            'isToday' => $day->isSameDay(OpsClock::today()),
            'isFuture' => $day->greaterThan(OpsClock::today()),
            'counts' => $board->counts($day),
            'outstanding' => $board->outstanding($day, $personId),
            'completed' => $board->completed($day, $personId),
            'journal' => $board->journal($day, $this->shift ?: null, $personId),
            'journalLimit' => HandoverBoard::JOURNAL_LIMIT,
            'handovers' => Handover::query()
                ->where('operational_date', $day->format('Y-m-d'))
                ->with(['outgoingUser', 'incomingUser'])
                ->orderBy('submitted_at')
                ->get(),
            'people' => User::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'shifts' => config('operations.shifts'),
        ]);
    }
}
