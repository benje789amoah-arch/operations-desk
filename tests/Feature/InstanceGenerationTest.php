<?php

namespace Tests\Feature;

use App\Actions\GenerateDailyInstances;
use App\Enums\Recurrence;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstanceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function generate(string $date): int
    {
        return app(GenerateDailyInstances::class)->handle($date);
    }

    public function test_daily_definitions_get_one_instance_per_date(): void
    {
        $definition = ActivityDefinition::factory()->create(['scheduled_time' => '08:00', 'due_time' => '09:30']);

        $this->assertSame(1, $this->generate('2026-10-14'));

        $instance = ActivityInstance::sole();
        $this->assertTrue($instance->definition->is($definition));
        $this->assertSame('2026-10-14', $instance->operational_date->format('Y-m-d'));
        $this->assertSame('pending', $instance->status->value);
        $this->assertFalse($instance->hasUpdates());
        $this->assertSame('2026-10-14 08:00', $instance->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-15 00:00', $instance->closes_at->format('Y-m-d H:i'));
    }

    public function test_generation_is_idempotent(): void
    {
        ActivityDefinition::factory()->count(3)->create();

        $this->assertSame(3, $this->generate('2026-10-14'));
        $this->assertSame(0, $this->generate('2026-10-14'));
        $this->assertSame(0, $this->generate('2026-10-14'));

        $this->assertSame(3, ActivityInstance::count());
        $this->assertSame(1, \App\Models\AuditEvent::query()->where('event', 'activity.instances_generated')->count());
    }

    public function test_a_second_run_never_resets_an_instance_that_already_has_history(): void
    {
        ActivityDefinition::factory()->create();
        $this->generate('2026-10-14');
        $instance = ActivityInstance::sole();
        $instance->forceFill(['status' => 'done', 'version' => 3])->save();

        $this->generate('2026-10-14');

        $this->assertSame('done', $instance->fresh()->status->value);
        $this->assertSame(3, $instance->fresh()->version);
    }

    public function test_the_database_rejects_duplicate_instances_for_a_definition_and_date(): void
    {
        $instance = $this->activityInstance([], '2026-10-14');

        $this->expectException(UniqueConstraintViolationException::class);

        ActivityInstance::factory()->for($instance->definition, 'definition')->on('2026-10-14')->create();
    }

    public function test_recurrence_rules_decide_which_dates_get_instances(): void
    {
        $weekdays = ActivityDefinition::factory()->recurring(Recurrence::Weekdays)->create();
        $mondays = ActivityDefinition::factory()->recurring(Recurrence::Weekly, [1])->create();
        $monthly = ActivityDefinition::factory()->recurring(Recurrence::Monthly, [31])->create();
        $adHoc = ActivityDefinition::factory()->recurring(Recurrence::AdHoc)->create();

        $this->generate('2026-10-12'); // Monday
        $this->generate('2026-10-17'); // Saturday
        $this->generate('2026-11-30'); // Monday, and the last day of a 30-day month

        $dates = fn (ActivityDefinition $d) => $d->instances()->pluck('operational_date')->map(fn ($v) => substr($v, 0, 10))->sort()->values()->all();

        $this->assertSame(['2026-10-12', '2026-11-30'], $dates($weekdays));
        $this->assertSame(['2026-10-12', '2026-11-30'], $dates($mondays));
        $this->assertSame(['2026-11-30'], $dates($monthly));
        $this->assertSame([], $dates($adHoc));
    }

    public function test_archived_and_not_yet_started_definitions_are_skipped(): void
    {
        ActivityDefinition::factory()->archived()->create();
        ActivityDefinition::factory()->create(['starts_on' => '2026-10-20']);
        ActivityDefinition::factory()->create(['ends_on' => '2026-10-01']);

        $this->assertSame(0, $this->generate('2026-10-14'));
    }

    public function test_the_console_command_backfills_a_range_safely(): void
    {
        ActivityDefinition::factory()->create();

        $this->artisan('operations:generate-instances', ['--date' => '2026-10-14', '--days' => 3])->assertSuccessful();
        $this->artisan('operations:generate-instances', ['--date' => '2026-10-14', '--days' => 3])
            ->expectsOutputToContain('0 instance(s) created')
            ->assertSuccessful();

        $this->assertSame(3, ActivityInstance::count());
    }

    public function test_operational_dates_follow_the_configured_timezone(): void
    {
        config(['operations.timezone' => 'Africa/Nairobi']); // UTC+3
        ActivityDefinition::factory()->create(['scheduled_time' => '08:00']);

        // 22:30 UTC on the 13th is already 01:30 on the 14th in Nairobi.
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-13 22:30:00', 'UTC'));
        app(GenerateDailyInstances::class)->handle(\App\Support\OpsClock::today());

        $instance = ActivityInstance::sole();
        $this->assertSame('2026-10-14', $instance->operational_date->format('Y-m-d'));
        $this->assertSame('2026-10-14 05:00', $instance->scheduled_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-14 21:00', $instance->closes_at->utc()->format('Y-m-d H:i'));
    }
}
