<?php

namespace Tests\Feature;

use App\Actions\GenerateDailyInstances;
use App\Enums\Recurrence;
use App\Livewire\Definitions\DefinitionForm;
use App\Livewire\Definitions\DefinitionIndex;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\AuditEvent;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_a_definition_and_it_joins_todays_register(): void
    {
        $this->freezeAt('2026-10-14 07:00:00');
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Messaging & Telco']);

        Livewire::actingAs($admin)
            ->test(DefinitionForm::class)
            ->set('form.title', 'Daily SMS count in comparison to SMS count from logs')
            ->set('form.description', 'Compare the application count with the gateway log count.')
            ->set('form.category_id', (string) $category->id)
            ->set('form.priority', 'critical')
            ->set('form.recurrence', 'daily')
            ->set('form.scheduled_time', '08:00')
            ->set('form.due_time', '09:30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $definition = ActivityDefinition::sole();
        $this->assertSame('ACT-001', $definition->code);
        $this->assertSame($admin->id, $definition->created_by);
        $this->assertSame(Recurrence::Daily, $definition->recurrence);
        $this->assertSame(1, ActivityInstance::query()->where('activity_definition_id', $definition->id)->onDate('2026-10-14')->count());
        $this->assertDatabaseHas('audit_events', ['event' => 'definition.created', 'subject_label' => 'ACT-001']);
    }

    public function test_definition_validation_messages(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DefinitionForm::class)
            ->set('form.title', '')
            ->set('form.recurrence', 'weekly')
            ->set('form.weekdays', [])
            ->set('form.scheduled_time', '10:00')
            ->set('form.due_time', '09:00')
            ->set('form.starts_on', '2026-10-14')
            ->set('form.ends_on', '2026-10-01')
            ->call('save')
            ->assertHasErrors(['form.title', 'form.weekdays', 'form.due_time', 'form.ends_on']);

        $this->assertSame(0, ActivityDefinition::count());
    }

    public function test_editing_a_definition_is_audited_and_leaves_existing_records_untouched(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $admin = $this->admin();
        $instance = $this->activityInstance(['title' => 'Review of application error logs', 'scheduled_time' => '08:15'], '2026-10-13');
        $definition = $instance->definition;
        $originalSlot = $instance->scheduled_at->format('H:i');

        Livewire::actingAs($admin)
            ->test(DefinitionForm::class, ['definition' => $definition])
            ->assertSet('form.title', 'Review of application error logs')
            ->set('form.title', 'Review of application and gateway error logs')
            ->set('form.scheduled_time', '09:00')
            ->call('save')
            ->assertHasNoErrors();

        $event = AuditEvent::query()->where('event', 'definition.updated')->sole();
        $this->assertSame('Review of application error logs', $event->properties['before']['title']);
        $this->assertSame('09:00', $event->properties['after']['scheduled_time']);

        // Yesterday's instance keeps the schedule it was created with...
        $this->assertSame($originalSlot, $instance->fresh()->scheduled_at->format('H:i'));
        // ...while today's newly generated instance uses the edited schedule.
        $today = $definition->instances()->onDate('2026-10-14')->sole();
        $this->assertSame('09:00', $today->scheduled_at->format('H:i'));
    }

    public function test_archiving_stops_future_generation_but_keeps_history(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $instance = $this->activityInstance([], '2026-10-13');
        $definition = $instance->definition;

        Livewire::actingAs($this->admin())
            ->test(DefinitionIndex::class)
            ->call('toggleArchived', $definition->id)
            ->assertDispatched('toast');

        $this->assertTrue($definition->fresh()->isArchived());
        $this->assertSame(0, app(GenerateDailyInstances::class)->handle('2026-10-15'));
        $this->assertTrue($instance->fresh()->exists);
        $this->assertDatabaseHas('audit_events', ['event' => 'definition.archived']);
    }

    public function test_the_register_of_definitions_can_be_searched_and_filtered(): void
    {
        ActivityDefinition::factory()->create(['title' => 'Daily SMS count in comparison to SMS count from logs']);
        ActivityDefinition::factory()->create(['title' => 'Verification of scheduled application jobs']);
        ActivityDefinition::factory()->archived()->create(['title' => 'Legacy SMS gateway check']);

        Livewire::actingAs($this->support())
            ->test(DefinitionIndex::class)
            ->set('search', 'SMS')
            ->assertSee('Daily SMS count in comparison')
            ->assertDontSee('Verification of scheduled application jobs')
            ->assertDontSee('Legacy SMS gateway check')
            ->set('status', 'archived')
            ->assertSee('Legacy SMS gateway check');

        Livewire::actingAs($this->support())
            ->test(DefinitionIndex::class)
            ->set('search', 'sms count')
            ->assertSee('Daily SMS count in comparison')
            ->assertDontSee('Verification of scheduled application jobs')
            ->set('search', '')
            ->set('category', 'abc')
            ->assertDontSee('Verification of scheduled application jobs');
    }

    public function test_definitions_show_whether_they_are_active_upcoming_ended_or_archived(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $ended = ActivityDefinition::factory()->create(['title' => 'Legacy gateway reconciliation', 'starts_on' => '2026-01-01', 'ends_on' => '2026-09-30']);
        $upcoming = ActivityDefinition::factory()->create(['title' => 'New partner availability check', 'starts_on' => '2026-11-01']);
        $active = ActivityDefinition::factory()->create(['title' => 'Daily SMS count in comparison to SMS count from logs']);

        $this->assertSame('ended', $ended->lifecycle());
        $this->assertSame('upcoming', $upcoming->lifecycle());
        $this->assertSame('active', $active->lifecycle());

        $this->actingAs($this->support());
        $this->get(route('definitions.show', $ended))->assertSee('Ended 30 Sep 2026')->assertDontSee('chip-done', false);
        $this->get(route('definitions.index'))->assertSee('Ended 30 Sep 2026')->assertSee('Starts 01 Nov 2026');
    }
}
