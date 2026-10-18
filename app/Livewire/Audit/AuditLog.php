<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\UsesDeskPagination;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Ability;
use App\Support\AuditLogQuery;
use App\Support\OpsClock;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Restricted system audit log with a change inspector and on-demand
 * verification of the tamper-evident hash chain.
 */
#[Title('System audit log')]
class AuditLog extends Component
{
    use UsesDeskPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $actor = '';

    #[Url(except: '')]
    public string $search = '';

    public ?int $inspecting = null;

    /** @var array{valid: bool, checked: int, broken_at: int|null, reason: string|null, at: string}|null */
    public ?array $verification = null;

    public function mount(): void
    {
        $this->authorize(Ability::VIEW_AUDIT_LOG);

        $this->from = OpsClock::safeDate($this->from ?: OpsClock::today()->subDays(6)->format('Y-m-d'))->format('Y-m-d');
        $this->to = OpsClock::safeDate($this->to ?: null)->format('Y-m-d');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function preset(int $days): void
    {
        $this->from = OpsClock::today()->subDays($days - 1)->format('Y-m-d');
        $this->to = OpsClock::today()->format('Y-m-d');
        $this->resetPage();
    }

    public function inspect(int $id): void
    {
        $this->inspecting = $this->inspecting === $id ? null : $id;
    }

    public function verifyChain(AuditLogger $audit): void
    {
        $this->authorize(Ability::VIEW_AUDIT_LOG);

        $this->verification = $audit->verifyChain() + ['at' => ops_datetime(now(), true)];
    }

    /**
     * @return array<string, string>
     */
    private function filterValues(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'category' => $this->category, 'actor' => $this->actor, 'search' => $this->search];
    }

    public function render()
    {
        $base = AuditLogQuery::build($this->filterValues());

        return view('livewire.audit.audit-log', [
            'events' => (clone $base)->paginate(25),
            'stats' => [
                'total' => (clone $base)->count(),
                'security' => (clone $base)->where('event', 'like', 'auth.%')->where(fn ($q) => $q->where('event', 'like', '%failed%')->orWhere('event', 'like', '%locked%'))->count(),
                'changes' => (clone $base)->where(fn ($q) => $q->where('event', 'like', 'definition.%')->orWhere('event', 'like', 'personnel.%'))->count(),
                'exports' => (clone $base)->where('event', 'report.exported')->count(),
                'all_time' => AuditEvent::query()->count(),
            ],
            'categories' => AuditLogQuery::CATEGORIES,
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
