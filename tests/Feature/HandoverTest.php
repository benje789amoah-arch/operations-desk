<?php

namespace Tests\Feature;

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Livewire\Handover\HandoverCentre;
use App\Models\Handover;
use App\Services\HandoverBoard;
use App\Support\OpsClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_work_includes_carried_over_items_and_is_sorted_for_review(): void
    {
        $this->freezeAt('2026-10-14 15:00:00');
        $user = $this->support();
        $record = app(RecordActivityUpdate::class);

        $normal = $this->activityInstance(['title' => 'Normal pending today'], '2026-10-14');
        $critical = $this->activityInstance(['title' => 'Critical pending today', 'priority' => Priority::Critical], '2026-10-14');
        $flagged = $this->activityInstance(['title' => 'Flagged item'], '2026-10-14');
        $carried = $this->activityInstance(['title' => 'Carried over from Monday'], '2026-10-12');
        $tooOld = $this->activityInstance(['title' => 'Outside the carry-over window'], '2026-09-30');
        $done = $this->activityInstance(['title' => 'Already done'], '2026-10-14');

        $record->handle($flagged, $user, ActivityStatus::Pending, 'Waiting on the vendor; please chase at 17:00.', 0, requiresFollowUp: true);
        $record->handle($carried, $user, ActivityStatus::Pending, 'Still waiting for the bank statement.', 0);
        $record->handle($done, $user, ActivityStatus::Done, 'Completed without issues.', 0);

        $titles = app(HandoverBoard::class)->outstanding(OpsClock::today())->map(fn ($i) => $i->definition->title)->all();

        $this->assertSame(['Flagged item', 'Critical pending today', 'Carried over from Monday', 'Normal pending today'], $titles);
        $this->assertNotContains('Already done', $titles);
        $this->assertNotContains('Outside the carry-over window', $titles);
    }

    public function test_the_handover_centre_shows_latest_remarks_and_earlier_context(): void
    {
        $this->freezeAt('2026-10-14 15:00:00');
        $first = $this->support(['name' => 'Kwesi Appiah']);
        $second = $this->support(['name' => 'Abena Kyei']);
        $instance = $this->activityInstance(['title' => 'Daily SMS count in comparison to SMS count from logs']);
        $record = app(RecordActivityUpdate::class);
        $record->handle($instance, $first, ActivityStatus::Pending, 'Difference identified between the application count and log count.', 0);
        $record->handle($instance->fresh(), $second, ActivityStatus::Pending, 'Investigation is ongoing with the aggregator.', 1);

        Livewire::actingAs($this->support())
            ->test(HandoverCentre::class)
            ->assertSee('Daily SMS count in comparison to SMS count from logs')
            ->assertSee('Investigation is ongoing with the aggregator.')
            ->assertSee('Abena Kyei')
            ->assertSee('Difference identified between the application count and log count.')
            ->assertSee('1 item to carry forward');
    }

    public function test_handover_can_be_filtered_by_personnel(): void
    {
        $this->freezeAt('2026-10-14 15:00:00');
        $kwesi = $this->support();
        $mine = $this->activityInstance(['title' => 'Assigned to Kwesi'], null, ['assigned_to' => $kwesi->id]);
        $this->activityInstance(['title' => 'Someone else\'s work']);

        Livewire::actingAs($this->support())
            ->test(HandoverCentre::class)
            ->set('person', (string) $kwesi->id)
            ->assertSee('Assigned to Kwesi')
            ->assertDontSee('Someone else\'s work');
    }

    public function test_a_handover_is_submitted_with_a_snapshot_and_acknowledged_by_someone_else(): void
    {
        $this->freezeAt('2026-10-14 16:05:00');
        $this->activityInstance(['title' => 'Pending at handover']);
        $outgoing = $this->support();
        $incoming = $this->support();

        Livewire::actingAs($outgoing)
            ->test(HandoverCentre::class)
            ->set('handoverShift', 'morning')
            ->set('handoverNote', 'Please chase the aggregator before 18:00 about the SMS variance.')
            ->call('submit')
            ->assertHasNoErrors();

        $handover = Handover::sole();
        $this->assertSame($outgoing->id, $handover->outgoing_user_id);
        $this->assertSame(1, $handover->snapshot['counts']['outstanding']);
        $this->assertSame('Pending at handover', $handover->snapshot['outstanding'][0]['title']);
        $this->assertFalse($handover->isAcknowledged());

        // The author cannot acknowledge their own handover.
        Livewire::actingAs($outgoing)->test(HandoverCentre::class)->call('acknowledge', $handover->id)->assertForbidden();

        Livewire::actingAs($incoming)
            ->test(HandoverCentre::class)
            ->set("acknowledgementNotes.{$handover->id}", 'Reviewed with the outgoing lead.')
            ->call('acknowledge', $handover->id)
            ->assertHasNoErrors();

        $handover->refresh();
        $this->assertTrue($handover->isAcknowledged());
        $this->assertSame($incoming->id, $handover->incoming_user_id);
        $this->assertDatabaseHas('audit_events', ['event' => 'handover.submitted']);
        $this->assertDatabaseHas('audit_events', ['event' => 'handover.acknowledged', 'actor_id' => $incoming->id]);
    }

    public function test_a_shift_can_only_be_handed_over_once(): void
    {
        $this->freezeAt('2026-10-14 16:05:00');
        $component = Livewire::actingAs($this->support())->test(HandoverCentre::class);

        $component->set('handoverShift', 'morning')->set('handoverNote', 'First handover note with enough detail.')->call('submit')->assertHasNoErrors();
        $component->set('handoverShift', 'morning')->set('handoverNote', 'A second attempt for the same shift.')->call('submit')->assertHasErrors('handoverShift');

        $this->assertSame(1, Handover::count());
    }

    public function test_the_printable_summary_has_no_navigation_and_lists_outstanding_items(): void
    {
        $this->freezeAt('2026-10-14 15:00:00');
        $instance = $this->activityInstance(['title' => 'Review of failed transaction records']);
        app(RecordActivityUpdate::class)->handle($instance, $this->support(), ActivityStatus::Pending, 'Escalated two stuck transfers.', 0);

        $this->actingAs($this->support())
            ->get(route('handover.print', ['date' => '2026-10-14']))
            ->assertOk()
            ->assertSee('Handover summary')
            ->assertSee('Outstanding items (1)')
            ->assertSee('Escalated two stuck transfers.')
            ->assertSee('Prepared')
            ->assertDontSee('data-app-sidebar', false)
            ->assertDontSee('Main navigation');
    }

    public function test_handovers_can_only_be_submitted_for_today_or_yesterday(): void
    {
        $this->freezeAt('2026-10-14 00:20:00');
        $user = $this->support();

        // Just after midnight, the evening shift that ended at 00:00 is handed over for yesterday.
        Livewire::actingAs($user)->test(HandoverCentre::class)
            ->set('date', '2026-10-13')
            ->set('handoverShift', 'evening')
            ->set('handoverNote', 'Evening shift handover, submitted just after midnight.')
            ->call('submit')
            ->assertHasNoErrors();

        Livewire::actingAs($user)->test(HandoverCentre::class)
            ->set('date', '2026-10-10')
            ->assertSee('Handovers can only be submitted for today or yesterday')
            ->set('handoverShift', 'morning')
            ->set('handoverNote', 'An attempt to back-date a handover by several days.')
            ->call('submit')
            ->assertHasErrors('handoverDate');

        $this->assertSame(1, Handover::count());
    }
}
