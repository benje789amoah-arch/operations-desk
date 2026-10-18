<?php

namespace Tests\Feature;

use App\Actions\RecordActivityUpdate;
use App\Actions\ScheduleActivityInstance;
use App\Enums\ActivityStatus;
use App\Enums\Recurrence;
use App\Livewire\Reports\ReportCentre;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Reports\ActivityHistoryReport;
use App\Reports\CompletionTrendReport;
use App\Reports\DailyActivityReport;
use App\Reports\PendingActivitiesReport;
use App\Reports\PersonnelActivityReport;
use App\Reports\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reports must reflect stored history for the selected period, not today's
 * state, and every filter must narrow the database query.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $kwesi;

    private User $abena;

    private ActivityInstance $sms13;

    private ActivityInstance $jobs13;

    private ActivityInstance $sms14;

    /**
     * 13 Oct: SMS check left pending at close of day, resolved on 14 Oct.
     *         Jobs check done on the day.
     * 14 Oct: SMS check never updated.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->kwesi = $this->support(['name' => 'Kwesi Appiah']);
        $this->abena = $this->supervisor(['name' => 'Abena Kyei']);
        $record = app(RecordActivityUpdate::class);

        $this->freezeAt('2026-10-13 00:01:00');
        $this->sms13 = $this->activityInstance(['title' => 'Daily SMS count in comparison to SMS count from logs'], '2026-10-13');
        $this->jobs13 = $this->activityInstance(['title' => 'Verification of scheduled application jobs'], '2026-10-13');

        $this->freezeAt('2026-10-13 09:10:00');
        $record->handle($this->sms13, $this->kwesi, ActivityStatus::Pending, 'Difference identified between the application count and log count.', 0);
        $this->freezeAt('2026-10-13 09:30:00');
        $record->handle($this->jobs13, $this->kwesi, ActivityStatus::Done, 'All scheduled jobs completed.', 0);

        $this->freezeAt('2026-10-14 00:01:00');
        $this->sms14 = ActivityInstance::factory()->for($this->sms13->definition, 'definition')->on('2026-10-14')->create();

        $this->freezeAt('2026-10-14 10:15:00');
        $record->handle($this->sms13->fresh(), $this->abena, ActivityStatus::Done, 'Counts reconciled after the aggregator re-sent the logs.', 1);

        $this->freezeAt('2026-10-14 15:00:00');
    }

    private function filters(string $type, string $from, string $to, array $extra = []): ReportFilters
    {
        return ReportFilters::fromValidated(['type' => $type, 'from' => $from, 'to' => $to] + $extra, $this->abena);
    }

    public function test_the_date_range_is_validated(): void
    {
        Livewire::actingAs($this->abena)
            ->withQueryParams(['from' => '2026-10-14', 'to' => '2026-10-01'])
            ->test(ReportCentre::class, ['type' => 'daily'])
            ->assertHasErrors('to')
            ->assertSee('The end date cannot be earlier than the start date.')
            ->assertSee('Check the report filters');

        Livewire::actingAs($this->abena)
            ->withQueryParams(['from' => '2024-01-01', 'to' => '2026-10-14'])
            ->test(ReportCentre::class, ['type' => 'daily'])
            ->assertHasErrors('to')
            ->assertSee('Reports can cover at most');
    }

    public function test_daily_report_uses_status_at_close_of_day_and_current_status(): void
    {
        $report = app(DailyActivityReport::class);
        $rows = $report->results($this->filters('daily', '2026-10-13', '2026-10-13'))->get()->keyBy('id');

        $this->assertCount(2, $rows);
        $this->assertSame('pending', $rows[$this->sms13->id]->status_at_close);
        $this->assertSame(ActivityStatus::Done, $rows[$this->sms13->id]->status);
        $this->assertSame('done', $rows[$this->jobs13->id]->status_at_close);

        $summary = collect($report->summary($this->filters('daily', '2026-10-13', '2026-10-13')))->keyBy('label');
        $this->assertSame(2, $summary['Done now']['value']);
        $this->assertSame(1, $summary['Done by close of day']['value']);
    }

    public function test_daily_report_filters_narrow_the_query(): void
    {
        $report = app(DailyActivityReport::class);

        $this->assertCount(3, $report->results($this->filters('daily', '2026-10-13', '2026-10-14'))->get());
        $this->assertCount(1, $report->results($this->filters('daily', '2026-10-14', '2026-10-14'))->get());
        $this->assertCount(1, $report->results($this->filters('daily', '2026-10-13', '2026-10-14', ['status' => 'not_updated']))->get());
        $this->assertCount(2, $report->results($this->filters('daily', '2026-10-13', '2026-10-14', ['definition' => $this->sms13->activity_definition_id]))->get());
        $this->assertCount(1, $report->results($this->filters('daily', '2026-10-13', '2026-10-14', ['user' => $this->abena->id]))->get());
    }

    public function test_history_report_supports_operational_and_recorded_date_bases(): void
    {
        $report = app(ActivityHistoryReport::class);

        // Operational basis: the 14 Oct resolution belongs to the 13 Oct activity.
        $this->assertCount(3, $report->results($this->filters('history', '2026-10-13', '2026-10-13'))->get());
        // Recorded basis: only the two updates made on the 13th.
        $this->assertCount(2, $report->results($this->filters('history', '2026-10-13', '2026-10-13', ['basis' => 'recorded']))->get());
        // Personnel filter applies to who made the update.
        $rows = $report->results($this->filters('history', '2026-10-13', '2026-10-14', ['user' => $this->abena->id]))->get();
        $this->assertSame(['Counts reconciled after the aggregator re-sent the logs.'], $rows->pluck('remark')->all());
    }

    public function test_pending_report_reflects_the_state_at_the_cutoff_not_today(): void
    {
        $report = app(PendingActivitiesReport::class);

        // At the end of 13 Oct the SMS check was still pending (it was resolved on the 14th).
        $filters = $this->filters('pending', '2026-10-13', '2026-10-13');
        $rows = $report->results($filters)->get();
        $report->decorate($rows, $filters);

        $this->assertSame([$this->sms13->id], $rows->pluck('id')->all());
        $this->assertSame('Difference identified between the application count and log count.', $rows[0]->getRelation('cutoffUpdate')->remark);
        $this->assertSame('2026-10-14 00:00:00', $report->cutoff($filters)->format('Y-m-d H:i:s'));

        // For 14 Oct (cutoff = now) only the never-updated instance is outstanding.
        $today = $report->results($this->filters('pending', '2026-10-13', '2026-10-14'))->get();
        $this->assertSame([$this->sms14->id], $today->pluck('id')->all());
    }

    public function test_personnel_report_counts_distinct_activities_and_update_types(): void
    {
        $report = app(PersonnelActivityReport::class);

        $team = $report->results($this->filters('personnel', '2026-10-13', '2026-10-14'))->keyBy(fn ($row) => $row->user->id);
        $this->assertSame(2, $team[$this->kwesi->id]->updates);
        $this->assertSame(2, $team[$this->kwesi->id]->instances);
        $this->assertSame(1, $team[$this->kwesi->id]->done);
        $this->assertSame(1, $team[$this->kwesi->id]->pending);
        $this->assertSame(1, $team[$this->abena->id]->done);

        $mine = $report->results($this->filters('personnel', '2026-10-13', '2026-10-13', ['user' => $this->kwesi->id]))->get();
        $this->assertCount(2, $mine);
    }

    public function test_completion_trend_counts_each_day_at_its_close(): void
    {
        $rows = app(CompletionTrendReport::class)->results($this->filters('trend', '2026-10-12', '2026-10-14'))->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $this->assertSame(0, $rows['2026-10-12']->total);
        $this->assertSame(2, $rows['2026-10-13']->total);
        $this->assertSame(1, $rows['2026-10-13']->done);
        $this->assertSame(1, $rows['2026-10-13']->pending);
        $this->assertSame(50.0, $rows['2026-10-13']->rate);
        $this->assertSame(1, $rows['2026-10-14']->not_updated);
    }

    public function test_reports_show_an_empty_state_when_nothing_matches(): void
    {
        Livewire::actingAs($this->abena)
            ->withQueryParams(['from' => '2026-09-01', 'to' => '2026-09-05'])
            ->test(ReportCentre::class, ['type' => 'history'])
            ->assertHasNoErrors()
            ->assertSee('No activity updates were recorded for this period');
    }

    public function test_csv_export_matches_the_filters_and_is_audited(): void
    {
        $response = $this->actingAs($this->abena)->get(route('reports.export', ['type' => 'history', 'from' => '2026-10-13', 'to' => '2026-10-13', 'basis' => 'recorded']));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Difference identified between the application count and log count.', $csv);
        $this->assertStringContainsString('All scheduled jobs completed.', $csv);
        $this->assertStringNotContainsString('Counts reconciled after the aggregator', $csv);
        $this->assertDatabaseHas('audit_events', ['event' => 'report.exported', 'actor_id' => $this->abena->id]);
    }

    public function test_csv_export_escapes_values_that_spreadsheets_would_run_as_formulas(): void
    {
        app(RecordActivityUpdate::class)->handle($this->sms14, $this->kwesi, ActivityStatus::Pending, '=HYPERLINK("http://example.com","click")', 0);

        $csv = $this->actingAs($this->abena)
            ->get(route('reports.export', ['type' => 'history', 'from' => '2026-10-14', 'to' => '2026-10-14']))
            ->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(""http://example.com"",""click"")"', $csv);
    }

    public function test_invalid_export_filters_are_rejected(): void
    {
        $this->actingAs($this->abena)
            ->get(route('reports.export', ['type' => 'daily', 'from' => '2026-10-14', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_activities_scheduled_after_the_cutoff_are_not_counted_as_pending(): void
    {
        $definition = ActivityDefinition::factory()->create(['recurrence' => Recurrence::AdHoc]);
        app(ScheduleActivityInstance::class)->handle($definition, '2026-10-16', null, $this->abena);
        $this->freezeAt('2026-10-14 16:00:00');

        $report = app(PendingActivitiesReport::class);
        $rows = $report->results($this->filters('pending', '2026-10-13', '2026-10-20'))->get();

        $this->assertSame([$this->sms14->id], $rows->pluck('id')->all());
    }
}
