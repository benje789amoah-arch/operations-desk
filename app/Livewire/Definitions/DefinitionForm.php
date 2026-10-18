<?php

namespace App\Livewire\Definitions;

use App\Actions\SaveActivityDefinition;
use App\Livewire\Forms\DefinitionData;
use App\Models\ActivityDefinition;
use App\Models\Category;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create and edit activity definitions (administrators only; enforced by
 * the route and again on save).
 */
class DefinitionForm extends Component
{
    public DefinitionData $form;

    #[Locked]
    public ?int $definitionId = null;

    public function mount(?ActivityDefinition $definition = null): void
    {
        $definition?->exists ? $this->authorize('update', $definition) : $this->authorize('create', ActivityDefinition::class);

        $this->definitionId = $definition?->exists ? $definition->id : null;
        $this->form->fillFrom($definition?->exists ? $definition : null);
    }

    public function definition(): ?ActivityDefinition
    {
        return $this->definitionId ? ActivityDefinition::with(['creator', 'editor'])->findOrFail($this->definitionId) : null;
    }

    public function save(SaveActivityDefinition $action)
    {
        $definition = $this->definition();
        $definition ? $this->authorize('update', $definition) : $this->authorize('create', ActivityDefinition::class);

        $this->form->validate();

        $saved = $action->handle($definition, $this->form->toAttributes(), auth()->user());

        session()->flash('toast', $definition ? "{$saved->code} updated. Changes apply to future register entries." : "{$saved->code} created.");

        return $this->redirectRoute('definitions.show', $saved);
    }

    public function render()
    {
        $definition = $this->definition();

        return view('livewire.definitions.definition-form', [
            'definition' => $definition,
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name']),
            'people' => User::query()->active()->orderBy('name')->get(['id', 'name', 'designation']),
            'nextCode' => $definition?->code ?? ActivityDefinition::nextCode(),
        ])->title($definition ? "Edit {$definition->code}" : 'New activity definition');
    }
}
