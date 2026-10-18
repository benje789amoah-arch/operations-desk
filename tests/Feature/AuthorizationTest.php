<?php

namespace Tests\Feature;

use App\Livewire\Activities\DailyRegister;
use App\Livewire\Activities\InstanceDetail;
use App\Livewire\Definitions\DefinitionIndex;
use App\Livewire\Reports\ReportCentre;
use App\Models\ActivityDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Role permissions are enforced by the server. Hiding a button is never the
 * only protection, so these tests call routes and component actions directly.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function adminOnlyPages(): array
    {
        return [['/personnel'], ['/personnel/create'], ['/audit-log'], ['/audit-log/export'], ['/definitions/create']];
    }

    #[DataProvider('adminOnlyPages')]
    public function test_support_members_are_denied_administrator_pages(string $uri): void
    {
        $this->actingAs($this->support())->get($uri)->assertForbidden();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_supervisors_are_denied_administrator_pages(string $uri): void
    {
        $this->actingAs($this->supervisor())->get($uri)->assertForbidden();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_administrators_can_open_administrator_pages(string $uri): void
    {
        $this->actingAs($this->admin())->get($uri)->assertOk();
    }

    public function test_editing_a_definition_is_restricted_to_administrators(): void
    {
        $definition = ActivityDefinition::factory()->create();

        $this->actingAs($this->support())->get(route('definitions.edit', $definition))->assertForbidden();
        $this->actingAs($this->supervisor())->get(route('definitions.edit', $definition))->assertForbidden();
        $this->actingAs($this->admin())->get(route('definitions.edit', $definition))->assertOk();
    }

    public function test_every_role_can_view_operational_pages(): void
    {
        $instance = $this->activityInstance();

        foreach ([$this->support(), $this->supervisor(), $this->admin()] as $user) {
            $this->actingAs($user);
            foreach (['/', '/daily-register', '/definitions', '/handover', '/reports', '/reports/daily', '/reports/history', '/reports/pending', '/reports/personnel', route('activities.show', $instance), route('definitions.show', $instance->definition)] as $uri) {
                $this->get($uri)->assertOk();
            }
        }
    }

    public function test_the_management_trend_report_is_restricted(): void
    {
        $this->actingAs($this->support())->get('/reports/trend')->assertForbidden();
        $this->actingAs($this->supervisor())->get('/reports/trend')->assertOk();
    }

    public function test_csv_export_is_restricted_to_supervisors_and_administrators(): void
    {
        $query = ['from' => '2026-10-01', 'to' => '2026-10-14'];

        $this->actingAs($this->support())->get(route('reports.export', ['type' => 'daily'] + $query))->assertForbidden();
        $this->actingAs($this->supervisor())->get(route('reports.export', ['type' => 'daily'] + $query))->assertOk();
    }

    public function test_support_members_cannot_archive_definitions_through_the_component(): void
    {
        $definition = ActivityDefinition::factory()->create();

        Livewire::actingAs($this->support())
            ->test(DefinitionIndex::class)
            ->call('toggleArchived', $definition->id)
            ->assertForbidden();

        $this->assertFalse($definition->fresh()->isArchived());
    }

    public function test_support_members_cannot_schedule_ad_hoc_activities(): void
    {
        $definition = ActivityDefinition::factory()->create();

        Livewire::actingAs($this->support())
            ->test(DailyRegister::class)
            ->set('scheduleDefinition', (string) $definition->id)
            ->call('schedule')
            ->assertForbidden();
    }

    public function test_support_members_cannot_reassign_an_activity(): void
    {
        $instance = $this->activityInstance();
        $other = $this->support();

        Livewire::actingAs($this->support())
            ->test(InstanceDetail::class, ['instance' => $instance])
            ->set('assignee', (string) $other->id)
            ->call('reassign')
            ->assertForbidden();

        $this->assertNull($instance->fresh()->assigned_to);
    }

    public function test_a_support_members_personnel_report_is_scoped_to_themselves_in_the_query(): void
    {
        $me = $this->support();
        $colleague = $this->support();

        Livewire::actingAs($me)
            ->withQueryParams(['user' => $colleague->id, 'from' => '2026-10-01', 'to' => '2026-10-14'])
            ->test(ReportCentre::class, ['type' => 'personnel'])
            ->assertViewHas('filters', fn ($filters) => $filters->userId === $me->id);
    }

    public function test_deactivated_accounts_fail_every_ability(): void
    {
        $admin = User::factory()->administrator()->inactive()->create();

        $this->assertFalse($admin->can('manage-personnel'));
        $this->assertFalse($admin->can('record-updates'));
    }
}
