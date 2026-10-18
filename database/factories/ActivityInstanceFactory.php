<?php

namespace Database\Factories;

use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Support\OpsClock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityInstance>
 */
class ActivityInstanceFactory extends Factory
{
    public function definition(): array
    {
        $date = OpsClock::today();

        return [
            'activity_definition_id' => ActivityDefinition::factory(),
            'operational_date' => $date->format('Y-m-d'),
            'origin' => ActivityInstance::ORIGIN_SCHEDULE,
            'scheduled_at' => OpsClock::at($date, '08:00'),
            'closes_at' => OpsClock::dayBounds($date)[1],
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn () => [
            'operational_date' => $date,
            'scheduled_at' => OpsClock::at($date, '08:00'),
            'closes_at' => OpsClock::dayBounds($date)[1],
        ]);
    }
}
