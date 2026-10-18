<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes the system audit log and verifies its hash chain.
 *
 * Never pass passwords, tokens or other secrets in $properties: the audit
 * log is readable by administrators and is exported to CSV.
 */
class AuditLogger
{
    /**
     * Record an event performed by the authenticated user (if any).
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, string $description, ?Model $subject = null, ?string $subjectLabel = null, array $properties = [], ?User $actor = null): AuditEvent
    {
        return $this->write($actor ?? auth()->user(), $event, $description, $subject, $subjectLabel, $properties);
    }

    /**
     * Record an event performed by the system itself (scheduler, console).
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordSystem(string $event, string $description, ?Model $subject = null, ?string $subjectLabel = null, array $properties = []): AuditEvent
    {
        return $this->write(null, $event, $description, $subject, $subjectLabel, $properties);
    }

    /**
     * Recalculate every hash in id order and confirm each row links to the one before it.
     *
     * @return array{valid: bool, checked: int, broken_at: int|null, reason: string|null}
     */
    public function verifyChain(): array
    {
        $previousHash = null;
        $checked = 0;
        $result = null;

        AuditEvent::query()->orderBy('id')->chunk(500, function ($events) use (&$previousHash, &$checked, &$result) {
            foreach ($events as $event) {
                $checked++;

                if ($event->previous_hash !== $previousHash) {
                    $result = ['valid' => false, 'checked' => $checked, 'broken_at' => $event->id, 'reason' => 'The link to the previous event does not match. An event before this one was removed or altered.'];

                    return false;
                }

                if (! hash_equals($event->hash, static::hashFor($this->hashableAttributes($event)))) {
                    $result = ['valid' => false, 'checked' => $checked, 'broken_at' => $event->id, 'reason' => 'The stored content of this event no longer matches its hash.'];

                    return false;
                }

                $previousHash = $event->hash;
            }

            return true;
        });

        return $result ?? ['valid' => true, 'checked' => $checked, 'broken_at' => null, 'reason' => null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function hashFor(array $attributes): string
    {
        $canonical = [
            'previous_hash' => $attributes['previous_hash'] ?? null,
            'actor_id' => isset($attributes['actor_id']) ? (int) $attributes['actor_id'] : null,
            'actor_name' => $attributes['actor_name'] ?? null,
            'event' => (string) $attributes['event'],
            'subject_type' => $attributes['subject_type'] ?? null,
            'subject_id' => isset($attributes['subject_id']) ? (int) $attributes['subject_id'] : null,
            'subject_label' => $attributes['subject_label'] ?? null,
            'description' => (string) $attributes['description'],
            'properties' => static::canonicalise($attributes['properties'] ?? null),
            'ip_address' => $attributes['ip_address'] ?? null,
            'created_at' => (string) $attributes['created_at'],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function write(?User $actor, string $event, string $description, ?Model $subject, ?string $subjectLabel, array $properties): AuditEvent
    {
        return DB::transaction(function () use ($actor, $event, $description, $subject, $subjectLabel, $properties) {
            // Serialise writers on databases that support row locks so two
            // concurrent events cannot both chain to the same predecessor.
            $previous = $this->lockChainHead();
            $request = app()->runningInConsole() ? null : request();

            $attributes = [
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name ?? 'System',
                'event' => $event,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel,
                'description' => Str::limit($description, 250, '…'),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'created_at' => now()->utc()->format('Y-m-d H:i:s'),
                'previous_hash' => $previous?->hash,
            ];

            $attributes['hash'] = static::hashFor($attributes);

            return AuditEvent::create($attributes);
        });
    }

    /**
     * Lock the newest event: the one the next event chains to.
     *
     * Under PostgreSQL's READ COMMITTED isolation, a locking read that had to
     * wait for another writer returns the row it found before waiting, which
     * is no longer the newest once that writer commits. So read again until
     * the locked row is still the newest. MySQL locking reads already see the
     * newest committed row and SQLite has a single writer, so there the
     * second read simply confirms the first.
     */
    private function lockChainHead(): ?AuditEvent
    {
        $head = null;

        do {
            $locked = $head;
            $head = AuditEvent::query()->orderByDesc('id')->lockForUpdate()->first(['id', 'hash']);
        } while ($head?->id !== $locked?->id);

        return $head;
    }

    /**
     * @return array<string, mixed>
     */
    private function hashableAttributes(AuditEvent $event): array
    {
        return [
            'previous_hash' => $event->previous_hash,
            'actor_id' => $event->actor_id,
            'actor_name' => $event->actor_name,
            'event' => $event->event,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'subject_label' => $event->subject_label,
            'description' => $event->description,
            'properties' => $event->properties,
            'ip_address' => $event->ip_address,
            'created_at' => $event->created_at->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sort keys recursively so JSON columns that normalise key order (MySQL)
     * still produce the same hash.
     */
    private static function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(static::canonicalise(...), $value);
    }
}
