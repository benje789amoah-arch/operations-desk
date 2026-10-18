<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filtering for the audit log screen and its CSV export.
 */
class AuditLogQuery
{
    public const CATEGORIES = [
        'activity' => 'Activity updates',
        'definition' => 'Activity definitions',
        'handover' => 'Handovers',
        'personnel' => 'Personnel & roles',
        'auth' => 'Sign-in & security',
        'report' => 'Report exports',
    ];

    /**
     * @param  array{from?: ?string, to?: ?string, category?: ?string, actor?: int|string|null, search?: ?string}  $filters
     * @return Builder<AuditEvent>
     */
    public static function build(array $filters): Builder
    {
        $from = OpsClock::safeDate($filters['from'] ?? null);
        $to = OpsClock::safeDate($filters['to'] ?? null);
        [$start, $end] = OpsClock::rangeBounds($from->min($to), $from->max($to));
        $search = trim((string) ($filters['search'] ?? ''));

        return AuditEvent::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when(array_key_exists($filters['category'] ?? '', self::CATEGORIES), fn (Builder $q) => $q->where('event', 'like', $filters['category'].'.%'))
            ->when(filled($filters['actor'] ?? null), fn (Builder $q) => $filters['actor'] === 'system'
                ? $q->whereNull('actor_id')
                : $q->where('actor_id', (int) $filters['actor']))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereLike('description', '%'.$search.'%')
                ->orWhereLike('subject_label', '%'.$search.'%')
                ->orWhereLike('actor_name', '%'.$search.'%')
                ->orWhereLike('event', '%'.$search.'%')
                ->orWhereLike('ip_address', '%'.$search.'%')))
            ->orderByDesc('id');
    }
}
