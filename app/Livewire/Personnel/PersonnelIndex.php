<?php

namespace App\Livewire\Personnel;

use App\Enums\Role;
use App\Livewire\Concerns\UsesDeskPagination;
use App\Models\User;
use App\Support\Ability;
use App\Support\OpsClock;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Personnel & access')]
class PersonnelIndex extends Component
{
    use UsesDeskPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        [$start] = OpsClock::dayBounds(OpsClock::today()->subDays(29));

        $people = User::query()
            ->withCount(['activityUpdates as updates_30d' => fn (Builder $q) => $q->where('created_at', '>=', $start)])
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereLike('name', '%'.$this->search.'%')
                ->orWhereLike('email', '%'.$this->search.'%')
                ->orWhereLike('staff_number', '%'.$this->search.'%')))
            ->when($this->role !== '', fn (Builder $q) => $q->where('role', $this->role))
            ->when($this->status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.personnel.personnel-index', [
            'people' => $people,
            'roleCounts' => User::query()->active()->toBase()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'activeCount' => User::query()->active()->count(),
            'inactiveCount' => User::query()->where('is_active', false)->count(),
            'matrix' => Ability::matrix(),
            'roles' => Role::cases(),
        ]);
    }
}
