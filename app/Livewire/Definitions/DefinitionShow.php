<?php

namespace App\Livewire\Definitions;

use App\Actions\SetDefinitionArchived;
use App\Models\ActivityDefinition;
use App\Models\AuditEvent;
use App\Support\OpsClock;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A definition with its recent register entries and change history.
 */
class DefinitionShow extends Component
{
    #[Locked]
    public int $definitionId;

    public function mount(ActivityDefinition $definition): void
    {
        $this->authorize('view', $definition);
        $this->definitionId = $definition->id;
    }

    public function toggleArchived(SetDefinitionArchived $action): void
    {
        $definition = ActivityDefinition::findOrFail($this->definitionId);
        $this->authorize('archive', $definition);

        $archive = ! $definition->isArchived();
        $action->handle($definition, $archive, auth()->user());

        $this->dispatch('toast', message: $archive ? "{$definition->code} archived. History is kept." : "{$definition->code} restored.", tone: $archive ? 'attention' : 'positive');
    }

    public function render()
    {
        $definition = ActivityDefinition::query()
            ->with(['category', 'defaultAssignee', 'creator', 'editor', 'archiver'])
            ->findOrFail($this->definitionId);

        $recent = $definition->instances()
            ->with(['latestUpdate', 'assignee', 'definition'])
            ->orderByDesc('operational_date')
            ->limit(14)
            ->get();

        $stats = $definition->instances()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'done' then 1 else 0 end) as done")
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending")
            ->first();

        $upcoming = collect(range(0, 13))
            ->map(fn (int $offset) => OpsClock::today()->addDays($offset))
            ->filter(fn ($date) => $definition->appliesOn($date))
            ->take(5);

        return view('livewire.definitions.definition-show', [
            'definition' => $definition,
            'recent' => $recent,
            'stats' => $stats,
            'upcoming' => $upcoming,
            'changes' => AuditEvent::query()
                ->where('subject_type', class_basename(ActivityDefinition::class))
                ->where('subject_id', $definition->id)
                ->latest('id')
                ->limit(10)
                ->get(),
        ])->title($definition->code.' · '.$definition->title);
    }
}
