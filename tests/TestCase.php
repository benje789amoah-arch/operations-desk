<?php

namespace Tests;

use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Freeze the clock at a local operational time (default: Wednesday 14 Oct 2026, 10:00).
     */
    protected function freezeAt(string $local = '2026-10-14 10:00:00'): CarbonImmutable
    {
        $moment = CarbonImmutable::parse($local, config('operations.timezone'));
        $this->travelTo($moment);

        return $moment;
    }

    protected function admin(array $attributes = []): User
    {
        return User::factory()->administrator()->create($attributes);
    }

    protected function supervisor(array $attributes = []): User
    {
        return User::factory()->supervisor()->create($attributes);
    }

    protected function support(array $attributes = []): User
    {
        return User::factory()->support()->create($attributes);
    }

    /**
     * An activity instance for a local operational date (default: today).
     */
    protected function activityInstance(array $definition = [], ?string $date = null, array $attributes = []): ActivityInstance
    {
        $date ??= OpsClock::today()->format('Y-m-d');

        return ActivityInstance::factory()
            ->for(ActivityDefinition::factory()->state($definition), 'definition')
            ->on($date)
            ->create($attributes);
    }
}
