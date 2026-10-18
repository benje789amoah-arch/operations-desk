<?php

namespace Tests\Feature;

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Enums\Recurrence;
use App\Livewire\Activities\DailyRegister;
use App\Livewire\Dashboard;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyRegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ActivityInstance, 1: ActivityInstance, 2: ActivityInstance}
     */
    private function threeStates(string $date = '2026-10-14'): array
    {
        $user = $this->support();
        $done = $this->activityInstance(['title' => 'Verification of service availability'], $date);
        $pending = $this->activityInstance(['title' => 'Review of failed transaction records'], $date);
        $untouched = $this->activityInstance(['title' => 'Database backup completion check'], $date);

        app(RecordActivityUpdate::class)->handle($done, $user, ActivityStatus::Done, 'All endpoints responded normally.', 0);
        app(RecordActivityUpdate::class)->handle($pending, $user, ActivityStatus::Pending, 'Two failed transfers escalated to the partner.', 0);

        return [$done, $pending, $untouched];
    }

    public function test_the_register_shows_the_selected_dates_activities_and_counts(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->threeStates();
        // Ad-hoc, so opening today's register does not also generate it for today.
        $this->activityInstance(['title' => 'An activity from another day', 'recurrence' => Recurrence::AdHoc], '2026-10-12');

        Livewire::actingAs($this->support())
            ->withQueryParams(['date' => '2026-10-14'])
            ->test(DailyRegister::class)
            ->assertSee('Verification of service availability')
            ->assertSee('Review of failed transaction records')
            ->assertSee('Database backup completion check')
            ->assertDontSee('An activity from another day')
            ->assertSee('Awaiting first update')
            ->assertSet('counts', fn ($counts) => $counts['total'] === 3 && $counts['done'] === 1 && $counts['pending'] === 1 && $counts['not_updated'] === 1);
    }

    public function test_never_updated_is_distinguished_from_updated_and_pending(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        [, $pending, $untouched] = $this->threeStates();

        $this->assertSame('pending', $pending->fresh()->displayState());
        $this->assertSame('not_updated', $untouched->fresh()->displayState());

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->set('state', 'not_updated')
            ->assertSee('Database backup completion check')
            ->assertDontSee('Review of failed transaction records')
            ->set('state', 'pending')
            ->assertSee('Review of failed transaction records')
            ->assertDontSee('Database backup completion check')
            ->set('state', 'done')
            ->assertSee('Verification of service availability')
            ->assertDontSee('Review of failed transaction records');
    }

    public function test_search_matches_titles_codes_and_latest_remarks(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->threeStates();

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->set('search', 'escalated to the partner')
            ->assertSee('Review of failed transaction records')
            ->assertDontSee('Verification of service availability');
    }

    public function test_search_ignores_letter_case_on_every_database(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->threeStates();

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->set('search', 'DATABASE BACKUP')
            ->assertSee('Database backup completion check')
            ->assertDontSee('Review of failed transaction records')
            ->set('search', 'Escalated')
            ->assertSee('Review of failed transaction records')
            ->assertDontSee('Database backup completion check');
    }

    public function test_a_malformed_filter_in_the_address_matches_nothing_instead_of_failing(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->threeStates();

        Livewire::actingAs($this->support())
            ->withQueryParams(['category' => 'abc', 'person' => 'abc'])
            ->test(DailyRegister::class)
            ->assertDontSee('Review of failed transaction records');
    }

    public function test_activities_without_a_scheduled_time_are_listed_after_timed_ones(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->activityInstance(['title' => 'Untimed reconciliation review', 'scheduled_time' => null], '2026-10-14', ['scheduled_at' => null]);
        $this->activityInstance(['title' => 'Timed availability check'], '2026-10-14');

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->assertViewHas('instances', fn ($page) => $page->getCollection()->map(fn (ActivityInstance $i) => $i->definition->title)->all()
                === ['Timed availability check', 'Untimed reconciliation review']);
    }

    public function test_previous_and_next_day_navigation(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        $this->activityInstance(['title' => 'Monday check'], '2026-10-13');

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->assertSet('date', '2026-10-14')
            ->call('shiftDay', -1)
            ->assertSet('date', '2026-10-13')
            ->assertSee('Monday check')
            ->call('goToToday')
            ->assertSet('date', '2026-10-14');
    }

    public function test_opening_todays_register_generates_missing_instances_once(): void
    {
        $this->freezeAt('2026-10-14 06:00:00');
        ActivityDefinition::factory()->create(['title' => 'Verification of scheduled application jobs']);

        Livewire::actingAs($this->support())->test(DailyRegister::class)->assertSee('Verification of scheduled application jobs');
        Livewire::actingAs($this->support())->test(DailyRegister::class);

        $this->assertSame(1, ActivityInstance::count());
    }

    public function test_empty_states_explain_the_situation(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->assertSee('No activities were scheduled for this date');

        Livewire::actingAs($this->support())
            ->withQueryParams(['date' => '2026-10-20'])
            ->test(DailyRegister::class)
            ->assertSee('Activities for this date are created on the day');

        Livewire::actingAs($this->support())
            ->test(Dashboard::class)
            ->assertSee('No activities are scheduled for today')
            ->assertSee('Nothing needs follow-up right now');
    }

    public function test_dashboard_metrics_count_each_activity_once(): void
    {
        $this->freezeAt('2026-10-14 12:00:00');
        [$done] = $this->threeStates();
        // A second update on the same activity must not inflate "updated today".
        app(RecordActivityUpdate::class)->handle($done->fresh(), $this->support(), ActivityStatus::Done, 'Re-checked after lunch; still healthy.', 1);

        Livewire::actingAs($this->support())
            ->test(Dashboard::class)
            ->assertViewHas('metrics', fn ($m) => $m['expected'] === 3 && $m['done'] === 1 && $m['pending'] === 1 && $m['not_updated'] === 1 && $m['updated'] === 2 && $m['updates'] === 3);
    }
}
