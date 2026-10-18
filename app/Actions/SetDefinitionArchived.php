<?php

namespace App\Actions;

use App\Models\ActivityDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Illuminate\Support\Facades\DB;

/**
 * Archives or restores a definition. Archiving stops future generation only;
 * existing instances, including any still pending, stay on the register and
 * in reports with their full history.
 */
class SetDefinitionArchived
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GenerateDailyInstances $generator,
    ) {}

    public function handle(ActivityDefinition $definition, bool $archived, User $actor): ActivityDefinition
    {
        if ($definition->isArchived() === $archived) {
            return $definition;
        }

        return DB::transaction(function () use ($definition, $archived, $actor) {
            $definition->forceFill([
                'archived_at' => $archived ? now() : null,
                'archived_by' => $archived ? $actor->id : null,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit->record(
                event: $archived ? 'definition.archived' : 'definition.restored',
                description: sprintf('%s activity definition "%s"', $archived ? 'Archived' : 'Restored', $definition->title),
                subject: $definition,
                subjectLabel: $definition->code,
                properties: ['before' => ['state' => $archived ? 'active' : 'archived'], 'after' => ['state' => $archived ? 'archived' : 'active']],
                actor: $actor,
            );

            if (! $archived) {
                $this->generator->handle(OpsClock::today(), $definition);
            }

            return $definition;
        });
    }
}
