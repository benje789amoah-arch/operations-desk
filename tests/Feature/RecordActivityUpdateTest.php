<?php

namespace Tests\Feature;

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Exceptions\StaleActivityUpdateException;
use App\Livewire\Activities\RecordUpdateForm;
use App\Models\ActivityInstance;
use App\Models\ActivityUpdate;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class RecordActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function record(ActivityInstance $instance, $user, string $status, string $remark): void
    {
        Livewire::actingAs($user)
            ->test(RecordUpdateForm::class, ['instance' => $instance->fresh()])
            ->set('status', $status)
            ->set('remark', $remark)
            ->call('save')
            ->assertStatus(200)
            ->assertHasNoErrors()
            ->assertSet('conflict', null);
    }

    public function test_a_done_update_records_the_authenticated_user_details_and_server_time(): void
    {
        $now = $this->freezeAt('2026-10-14 09:42:18');
        $user = $this->support(['name' => 'Ama Mensah', 'staff_number' => 'NP-804', 'designation' => 'Senior Operations Analyst']);
        $instance = $this->activityInstance();

        Livewire::actingAs($user)
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->set('status', 'done')
            ->set('remark', 'SMS count from application records matched the count extracted from the logs.')
            ->set('supportingDetails', 'App 142,850 / Logs 142,850')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('activity-updated');

        $update = ActivityUpdate::sole();
        $this->assertSame($user->id, $update->user_id);
        $this->assertSame('Ama Mensah', $update->updater_name);
        $this->assertSame('NP-804', $update->updater_staff_number);
        $this->assertSame('Senior Operations Analyst', $update->updater_designation);
        $this->assertSame($user->email, $update->updater_email);
        $this->assertNull($update->previous_status);
        $this->assertSame(ActivityStatus::Done, $update->new_status);
        $this->assertSame($now->utc()->format('Y-m-d H:i:s'), $update->created_at->utc()->format('Y-m-d H:i:s'));

        $instance->refresh();
        $this->assertSame(ActivityStatus::Done, $instance->status);
        $this->assertSame($update->id, $instance->latest_update_id);
        $this->assertSame($user->id, $instance->last_updated_by);
        $this->assertSame(1, $instance->version);
        $this->assertDatabaseHas('audit_events', ['event' => 'activity.completed', 'subject_id' => $instance->id, 'actor_id' => $user->id]);
    }

    public function test_the_updater_identity_cannot_be_supplied_by_the_browser(): void
    {
        $instance = $this->activityInstance();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->set('instanceId', 999);
    }

    public function test_a_remark_and_an_explicit_status_are_required(): void
    {
        $instance = $this->activityInstance();

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->call('save')
            ->assertHasErrors(['status' => 'required', 'remark' => 'required']);

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->set('status', 'done')
            ->set('remark', 'ok')
            ->call('save')
            ->assertHasErrors(['remark' => 'min']);

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->set('status', 'finished')
            ->set('remark', 'A long enough remark for the record.')
            ->call('save')
            ->assertHasErrors('status');

        $this->assertSame(0, ActivityUpdate::count());
        $this->assertSame(0, $instance->fresh()->version);
    }

    public function test_a_pending_update_is_not_treated_as_completion(): void
    {
        $instance = $this->activityInstance();

        $this->record($instance, $this->support(), 'pending', 'Difference identified between the application count and log count. Further investigation is required.');

        $instance->refresh();
        $this->assertSame(ActivityStatus::Pending, $instance->status);
        $this->assertTrue($instance->hasUpdates());
        $this->assertSame('pending', $instance->displayState());
        $this->assertFalse($instance->isDone());
    }

    public function test_every_update_and_remark_is_preserved(): void
    {
        $this->freezeAt('2026-10-14 08:00:00');
        $instance = $this->activityInstance();
        $first = $this->support();
        $second = $this->support();

        $this->freezeAt('2026-10-14 09:00:00');
        $this->record($instance, $first, 'pending', 'Gateway log extract incomplete; re-extract requested.');
        $this->freezeAt('2026-10-14 11:30:00');
        $this->record($instance, $second, 'pending', 'Investigation is ongoing. The discrepancy has not yet been resolved.');
        $this->freezeAt('2026-10-14 14:05:00');
        $this->record($instance, $first, 'done', 'Counts now match after the aggregator re-sent the logs.');

        $updates = $instance->updates()->orderBy('id')->get();
        $this->assertCount(3, $updates);
        $this->assertSame(['Gateway log extract incomplete; re-extract requested.', 'Investigation is ongoing. The discrepancy has not yet been resolved.', 'Counts now match after the aggregator re-sent the logs.'], $updates->pluck('remark')->all());
        $this->assertSame([null, 'pending', 'pending'], $updates->map(fn ($u) => $u->previous_status?->value)->all());
        $this->assertSame([$first->id, $second->id, $first->id], $updates->pluck('user_id')->all());
        $this->assertSame(3, $instance->fresh()->version);
    }

    public function test_reopening_a_completed_activity_keeps_the_completion_event(): void
    {
        $instance = $this->activityInstance();
        $user = $this->support();

        $this->record($instance, $user, 'done', 'Backups completed and copied to secondary storage.');
        $this->record($instance, $this->supervisor(), 'pending', 'Reopening: the secondary copy was found to be incomplete.');

        $updates = $instance->updates()->orderBy('id')->get();
        $this->assertSame(ActivityStatus::Done, $updates[0]->new_status);
        $this->assertTrue($updates[1]->isReopening());
        $this->assertSame('Reopened', $updates[1]->eventLabel());
        $this->assertSame(ActivityStatus::Pending, $instance->fresh()->status);
        $this->assertTrue($instance->fresh()->wasReopened());
        $this->assertDatabaseHas('audit_events', ['event' => 'activity.reopened', 'subject_id' => $instance->id]);
    }

    public function test_a_stale_update_does_not_overwrite_someone_elses_newer_update(): void
    {
        $instance = $this->activityInstance();

        $kwesi = $this->support(['name' => 'Kwesi Appiah']);
        $yaw = $this->support(['name' => 'Yaw Darko']);

        // Both people open the form while the activity is at version 0.
        $mine = Livewire::actingAs($kwesi)->test(RecordUpdateForm::class, ['instance' => $instance]);
        $theirs = Livewire::actingAs($yaw)->test(RecordUpdateForm::class, ['instance' => $instance]);

        $theirs->set('status', 'done')->set('remark', 'Checked: all jobs completed successfully.')->call('save')->assertHasNoErrors();

        $this->actingAs($kwesi);
        $mine->set('status', 'pending')->set('remark', 'One job failed overnight; investigating.')->call('save')
            ->assertSet('conflict', fn ($message) => str_contains($message, 'Yaw Darko recorded an update'))
            ->assertSet('remark', 'One job failed overnight; investigating.');

        $this->assertSame(1, ActivityUpdate::count());
        $this->assertSame(ActivityStatus::Done, $instance->fresh()->status);

        // After reviewing, the person can deliberately resubmit.
        $mine->call('acceptLatest')->call('save')->assertHasNoErrors();
        $this->assertSame(2, ActivityUpdate::count());
        $this->assertTrue(ActivityUpdate::latest('id')->first()->isReopening());
    }

    public function test_a_double_submission_cannot_create_two_updates(): void
    {
        $instance = $this->activityInstance();
        $user = $this->support();
        $action = app(RecordActivityUpdate::class);

        $action->handle($instance, $user, ActivityStatus::Done, 'All endpoints responded normally.', expectedVersion: 0);

        $this->expectException(StaleActivityUpdateException::class);
        $action->handle($instance, $user, ActivityStatus::Done, 'All endpoints responded normally.', expectedVersion: 0);
    }

    public function test_a_failure_part_way_through_leaves_no_partial_records(): void
    {
        $instance = $this->activityInstance();
        $user = $this->support();

        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function record(string $event, string $description, ?Model $subject = null, ?string $subjectLabel = null, array $properties = [], ?User $actor = null): AuditEvent
            {
                throw new RuntimeException('Simulated database failure while writing the audit log.');
            }
        });

        try {
            app(RecordActivityUpdate::class)->handle($instance, $user, ActivityStatus::Done, 'This should not be persisted.', expectedVersion: 0);
            $this->fail('The simulated failure should have been thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated database failure while writing the audit log.', $exception->getMessage());
        }

        $instance->refresh();
        $this->assertSame(0, ActivityUpdate::count());
        $this->assertSame(ActivityStatus::Pending, $instance->status);
        $this->assertNull($instance->latest_update_id);
        $this->assertSame(0, $instance->version);
    }

    public function test_activities_for_a_future_date_cannot_be_updated_yet(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $instance = $this->activityInstance([], '2026-10-15');

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->assertSee('Updates open on that day')
            ->set('status', 'done')
            ->set('remark', 'Trying to complete tomorrow\'s check early.')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, ActivityUpdate::count());
    }

    public function test_updates_are_append_only(): void
    {
        $instance = $this->activityInstance();
        $this->record($instance, $this->support(), 'done', 'Verified and complete for the day.');
        $update = ActivityUpdate::sole();

        try {
            $update->update(['remark' => 'Rewritten history']);
            $this->fail('Updates must not be editable.');
        } catch (LogicException) {
        }

        try {
            $update->delete();
            $this->fail('Updates must not be deletable.');
        } catch (LogicException) {
        }

        $this->assertSame('Verified and complete for the day.', $update->fresh()->remark);
    }

    public function test_the_updater_snapshot_survives_later_profile_changes(): void
    {
        $user = $this->support(['designation' => 'Applications Support Analyst']);
        $instance = $this->activityInstance();
        $this->record($instance, $user, 'done', 'All checks completed without issues.');

        $user->forceFill(['designation' => 'Team Lead', 'name' => 'Renamed Person'])->save();

        $update = ActivityUpdate::sole();
        $this->assertSame('Applications Support Analyst', $update->updater_designation);
        $this->assertNotSame('Renamed Person', $update->updater_name);
    }

    public function test_padding_cannot_satisfy_the_minimum_remark_length(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $instance = $this->activityInstance();

        Livewire::actingAs($this->support())
            ->test(RecordUpdateForm::class, ['instance' => $instance])
            ->set('status', 'done')
            ->set('remark', '         x')
            ->call('save')
            ->assertHasErrors(['remark' => 'min']);

        $this->assertSame(0, ActivityUpdate::count());
    }
}
