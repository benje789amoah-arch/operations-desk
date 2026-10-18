<?php

namespace App\Livewire\Definitions;

use App\Actions\SetDefinitionArchived;
use App\Livewire\Concerns\UsesDeskPagination;
use App\Models\ActivityDefinition;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\Ability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Activity definitions')]
class DefinitionIndex extends Component
{
    use UsesDeskPagination;

    private const SORTS = ['code', 'title', 'created_at', 'updated_at', 'priority'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $recurrence = '';

    #[Url(except: 'code')]
    public string $sort = 'code';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public string $newCategory = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'recurrence'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTS, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'recurrence']);
        $this->resetPage();
    }

    public function toggleArchived(int $id, SetDefinitionArchived $action): void
    {
        $definition = ActivityDefinition::findOrFail($id);
        $this->authorize('archive', $definition);

        $archive = ! $definition->isArchived();
        $action->handle($definition, $archive, auth()->user());

        $this->dispatch('toast',
            message: $archive ? "{$definition->code} archived. Existing records are kept; no new instances will be created." : "{$definition->code} restored and scheduling resumed.",
            tone: $archive ? 'attention' : 'positive');
    }

    public function addCategory(AuditLogger $audit): void
    {
        $this->authorize(Ability::MANAGE_DEFINITIONS);

        $validated = $this->validate(
            ['newCategory' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')]],
            ['newCategory.unique' => 'That category already exists.', 'newCategory.required' => 'Enter a category name.'],
        );

        $category = Category::create(['name' => trim($validated['newCategory'])]);
        $audit->record('definition.category_created', "Created category \"{$category->name}\"", $category, $category->name);

        $this->reset('newCategory');
        $this->dispatch('toast', message: "Category \"{$category->name}\" added.");
    }

    public function render()
    {
        $definitions = ActivityDefinition::query()
            ->with(['category', 'defaultAssignee'])
            ->withCount('instances')
            ->when($this->status === 'active', fn (Builder $q) => $q->active())
            ->when($this->status === 'archived', fn (Builder $q) => $q->archived())
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('code', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%')))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category_id', $this->category))
            ->when($this->recurrence !== '', fn (Builder $q) => $q->where('recurrence', $this->recurrence))
            ->orderBy(in_array($this->sort, self::SORTS, true) ? $this->sort : 'code', $this->direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(15);

        return view('livewire.definitions.definition-index', [
            'definitions' => $definitions,
            'categories' => Category::query()->withCount('definitions')->orderBy('name')->get(),
            'totals' => [
                'active' => ActivityDefinition::query()->active()->count(),
                'archived' => ActivityDefinition::query()->archived()->count(),
            ],
        ]);
    }
}
