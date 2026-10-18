<?php

namespace App\Actions;

use App\Models\ActivityDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits an activity definition and audits the change.
 *
 * Edits apply to instances generated from now on. Instances that already
 * exist (including today's) keep their history; if the change makes the
 * definition due today and today's instance does not exist yet, it is created.
 */
class SaveActivityDefinition
{
    /**
     * Attributes shown in the audit diff.
     */
    private const AUDITED = [
        'title', 'description', 'category_id', 'priority', 'recurrence', 'recurrence_days',
        'scheduled_time', 'due_time', 'default_assignee_id', 'starts_on', 'ends_on',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GenerateDailyInstances $generator,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated attributes.
     */
    public function handle(?ActivityDefinition $definition, array $data, User $actor): ActivityDefinition
    {
        return DB::transaction(function () use ($definition, $data, $actor) {
            $creating = $definition === null;
            $definition ??= new ActivityDefinition(['code' => ActivityDefinition::nextCode(), 'created_by' => $actor->id]);

            $before = $creating ? [] : $this->auditable($definition);
            $definition->fill([...$data, 'updated_by' => $actor->id])->save();
            $after = $this->auditable($definition->refresh());

            $changedBefore = array_diff_assoc(array_map('json_encode', $before), array_map('json_encode', $after));
            $changedKeys = $creating ? array_keys($after) : array_keys($changedBefore);

            if ($creating || $changedKeys !== []) {
                $this->audit->record(
                    event: $creating ? 'definition.created' : 'definition.updated',
                    description: sprintf('%s activity definition "%s"', $creating ? 'Created' : 'Updated', $definition->title),
                    subject: $definition,
                    subjectLabel: $definition->code,
                    properties: [
                        'before' => Arr::only($before, $changedKeys),
                        'after' => Arr::only($after, $changedKeys),
                    ],
                    actor: $actor,
                );
            }

            $this->generator->handle(OpsClock::today(), $definition);

            return $definition;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditable(ActivityDefinition $definition): array
    {
        $definition->loadMissing(['category', 'defaultAssignee']);

        return [
            'title' => $definition->title,
            'description' => $definition->description,
            'category_id' => $definition->category?->name,
            'priority' => $definition->priority?->value,
            'recurrence' => $definition->recurrence?->value,
            'recurrence_days' => $definition->recurrence_days ? implode(',', $definition->recurrence_days) : null,
            'scheduled_time' => $definition->scheduled_time ? substr($definition->scheduled_time, 0, 5) : null,
            'due_time' => $definition->due_time ? substr($definition->due_time, 0, 5) : null,
            'default_assignee_id' => $definition->defaultAssignee?->name,
            'starts_on' => $definition->starts_on?->format('Y-m-d'),
            'ends_on' => $definition->ends_on?->format('Y-m-d'),
        ];
    }
}
