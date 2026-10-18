<?php

namespace App\Livewire\Activities;

use App\Actions\GenerateDailyInstances;
use App\Actions\ScheduleActivityInstance;
use App\Enums\ActivityStatus;
use App\Livewire\Concerns\UsesDeskPagination;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\Category;
use App\Models\User;
use App\Services\HandoverBoard;
use App\Support\Ability;
use App\Support\InstanceTimeline;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The daily activity register: every instance for one operational date,
 * with filters, counts and a side panel to record an update.
 */
#[Title('Daily register')]
class DailyRegister extends Component
{
    use UsesDeskPagination;

    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $person = '';

    #[Url(as: 'activity', except: null)]
    public ?int $selected = null;

    public bool $scheduling = false;

    public string $scheduleDefinition = '';

    public string $scheduleAssignee = '';

    public function mount(): void
    {
        $this->showDate(OpsClock::safeDate($this->date ?: null), keepSelection: true);
    }

    public function day(): CarbonImmutable
    {
        return OpsClock::safeDate($this->date);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'state', 'category', 'person'], true)) {
            $this->resetPage();
        }

        if ($property === 'date') {
            $this->showDate($this->day());
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
     * Switch the register to a date. Generation is idempotent, so opening
     * today's register is a safe fallback if the scheduler has not run yet.
     */
    private function showDate(CarbonImmutable $date, bool $keepSelection = false): void
    {
        $this->date = $date->format('Y-m-d');

        if ($date->isSameDay(OpsClock::today())) {
            app(GenerateDailyInstances::class)->handle($date);
        }

        if (! $keepSelection) {
            $this->selected = null;
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'state', 'category', 'person']);
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->selected = $id;
    }

    #[On('update-form-cancelled')]
    public function closePanel(): void
    {
        $this->selected = null;
    }

    #[On('activity-updated')]
    public function refreshAfterUpdate(): void
    {
        unset($this->counts, $this->selectedInstance);
    }

    public function schedule(ScheduleActivityInstance $action): void
    {
        $this->authorize(Ability::SCHEDULE_ACTIVITIES);

        $this->validate([
            'scheduleDefinition' => ['required', Rule::exists('activity_definitions', 'id')->whereNull('archived_at')],
            'scheduleAssignee' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
        ], [
            'scheduleDefinition.required' => 'Choose the activity to schedule.',
        ]);

        $definition = ActivityDefinition::findOrFail($this->scheduleDefinition);
        $this->authorize('schedule', $definition);

        $instance = $action->handle(
            $definition,
            $this->date,
            $this->scheduleAssignee ? User::find($this->scheduleAssignee) : null,
            auth()->user(),
        );

        $this->reset(['scheduling', 'scheduleDefinition', 'scheduleAssignee']);
        $this->selected = $instance->id;
        $this->dispatch('toast', message: 'Activity scheduled for '.$this->day()->format('d M Y').'.');
    }

    #[Computed]
    public function counts(): array
    {
        return app(HandoverBoard::class)->counts($this->day());
    }

    #[Computed]
    public function selectedInstance(): ?ActivityInstance
    {
        return $this->selected
            ? ActivityInstance::query()->withRowContext()->with(['updates', 'creator'])->find($this->selected)
            : null;
    }

    public function render()
    {
        $instances = ActivityInstance::query()
            ->onDate($this->day())
            ->withRowContext()
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereHas('definition', fn (Builder $d) => $d
                    ->whereLike('title', '%'.$this->search.'%')
                    ->orWhereLike('code', '%'.$this->search.'%')
                    ->orWhereLike('description', '%'.$this->search.'%'))
                ->orWhereHas('latestUpdate', fn (Builder $u) => $u->whereLike('remark', '%'.$this->search.'%'))))
            ->when($this->state === 'done', fn (Builder $q) => $q->where('status', ActivityStatus::Done))
            ->when($this->state === 'pending', fn (Builder $q) => $q->where('status', ActivityStatus::Pending)->whereNotNull('latest_update_id'))
            ->when($this->state === 'not_updated', fn (Builder $q) => $q->whereNull('latest_update_id'))
            ->when($this->state === 'follow_up', fn (Builder $q) => $q->where('requires_follow_up', true)->where('status', ActivityStatus::Pending))
            ->when($this->category !== '', fn (Builder $q) => $q->whereHas('definition', fn (Builder $d) => $d->where('category_id', (int) $this->category)))
            ->when($this->person !== '', fn (Builder $q) => $q->where('assigned_to', (int) $this->person))
            ->orderByRaw('case when latest_update_id is null then 0 when status = ? then 1 else 2 end', [ActivityStatus::Pending->value])
            ->inScheduleOrder()
            ->orderBy('id')
            ->paginate(25);

        $isFuture = $this->day()->greaterThan(OpsClock::today());

        return view('livewire.activities.daily-register', [
            'instances' => $instances,
            'isToday' => $this->day()->isSameDay(OpsClock::today()),
            'isFuture' => $isFuture,
            'scheduledForFuture' => $isFuture
                ? ActivityDefinition::query()->active()->get()->filter(fn (ActivityDefinition $d) => $d->appliesOn($this->day()))->count()
                : 0,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'people' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'definitions' => auth()->user()->can(Ability::SCHEDULE_ACTIVITIES)
                ? ActivityDefinition::query()->active()->orderBy('code')->get(['id', 'code', 'title'])
                : collect(),
            'timeline' => $this->selectedInstance ? InstanceTimeline::for($this->selectedInstance) : collect(),
            'filtersActive' => $this->search !== '' || $this->state !== '' || $this->category !== '' || $this->person !== '',
        ]);
    }
}
