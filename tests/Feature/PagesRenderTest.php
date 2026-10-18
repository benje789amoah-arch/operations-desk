<?php

namespace Tests\Feature;

use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen renders with a realistic dataset (the demo seeder replays
 * three weeks of history through the real business actions) and with an
 * empty database.
 */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_with_the_demo_dataset(): void
    {
        $this->freezeAt('2026-10-14 15:30:00');
        $this->seed([CategorySeeder::class, DemoDataSeeder::class]);

        $admin = User::query()->where('email', 'kofi.boateng@example.com')->sole();
        $instance = ActivityInstance::query()->whereNotNull('latest_update_id')->firstOrFail();
        $definition = ActivityDefinition::query()->firstOrFail();
        $range = ['from' => '2026-10-01', 'to' => '2026-10-14'];

        $pages = [
            route('dashboard'),
            route('register'),
            route('register', ['date' => '2026-10-13', 'activity' => $instance->id]),
            route('activities.show', $instance),
            route('definitions.index'),
            route('definitions.show', $definition),
            route('definitions.edit', $definition),
            route('definitions.create'),
            route('handover.index'),
            route('handover.index', ['date' => '2026-10-13', 'shift' => 'morning']),
            route('handover.print', ['date' => '2026-10-13']),
            route('reports.index'),
            route('reports.show', ['type' => 'daily'] + $range),
            route('reports.show', ['type' => 'history'] + $range),
            route('reports.show', ['type' => 'pending'] + $range),
            route('reports.show', ['type' => 'personnel'] + $range),
            route('reports.show', ['type' => 'trend'] + $range),
            route('personnel.index'),
            route('personnel.edit', $admin),
            route('personnel.create'),
            route('audit.index'),
            route('profile.show'),
            route('password.edit'),
        ];

        $this->actingAs($admin);
        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }

        $this->assertTrue(app(AuditLogger::class)->verifyChain()['valid']);
    }

    public function test_core_pages_render_with_an_empty_database(): void
    {
        $this->actingAs($this->admin());

        foreach (['/', '/daily-register', '/definitions', '/handover', '/handover/print', '/reports', '/reports/daily', '/reports/trend', '/personnel', '/audit-log'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_unknown_records_show_the_not_found_page(): void
    {
        $this->actingAs($this->support())
            ->get('/activities/999999')
            ->assertNotFound()
            ->assertSee('We could not find that page');
    }

    public function test_forbidden_pages_show_the_access_denied_page(): void
    {
        $this->actingAs($this->support())
            ->get('/personnel')
            ->assertForbidden()
            ->assertSee('You do not have access to this page');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_opening_a_form_only_address_shows_a_friendly_page(): void
    {
        $this->actingAs($this->support())
            ->get('/logout')
            ->assertStatus(405)
            ->assertSee('That page cannot be opened directly');
    }

    public function test_filter_buttons_use_toggle_semantics_not_tab_roles(): void
    {
        $this->actingAs($this->support())
            ->get('/')
            ->assertSee('aria-pressed="true"', false)
            ->assertDontSee('role="tab"', false);
    }
}
