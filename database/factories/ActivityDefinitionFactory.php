<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\Recurrence;
use App\Models\ActivityDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityDefinition>
 */
class ActivityDefinitionFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'code' => sprintf('ACT-%03d', $sequence),
            'title' => fake()->unique()->sentence(5),
            'description' => fake()->paragraph(),
            'category_id' => null,
            'priority' => Priority::Normal,
            'recurrence' => Recurrence::Daily,
            'recurrence_days' => null,
            'scheduled_time' => '08:00',
            'due_time' => null,
            'default_assignee_id' => null,
            'starts_on' => now()->subYear()->format('Y-m-d'),
            'ends_on' => null,
            'created_by' => User::factory()->administrator(),
        ];
    }

    public function recurring(Recurrence $recurrence, ?array $days = null): static
    {
        return $this->state(fn () => ['recurrence' => $recurrence, 'recurrence_days' => $days]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
