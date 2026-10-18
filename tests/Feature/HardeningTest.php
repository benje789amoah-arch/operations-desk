<?php

namespace Tests\Feature;

use App\Support\OperationsConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session security, input hardening, configuration validation and
 * operational headers added during the final review.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_change_elsewhere_signs_out_this_session(): void
    {
        $user = $this->support(['email' => 'kwesi@example.com']);

        $this->post('/login', ['email' => 'kwesi@example.com', 'password' => 'Password-2026']);
        $this->get('/')->assertOk();

        // The password is changed from another device or by a reset link.
        $user->forceFill(['password' => 'Changed-Elsewhere-1'])->save();

        // Each real request loads the user afresh; clear the guard's in-memory copy to match.
        $this->app['auth']->forgetGuards();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tampered_query_strings_are_rejected_instead_of_crashing(): void
    {
        $this->actingAs($this->admin());

        $this->get('/handover/print?date[]=2026-10-14')->assertRedirect();
        $this->get('/handover/print?shift=lunch')->assertRedirect();
        $this->get('/handover/print?date=2026-10-14&shift=morning')->assertOk();

        $this->get('/audit-log/export?search[]=x')->assertRedirect();
        $this->get('/audit-log/export?actor=1%20or%201=1')->assertRedirect();
        $this->get('/audit-log/export?actor=system')->assertOk();
    }

    public function test_security_and_tracing_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-Id'));
    }

    public function test_the_content_security_policy_can_be_disabled(): void
    {
        config(['app.content_security_policy' => false]);

        $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_invalid_operations_configuration_is_reported_precisely(): void
    {
        $this->assertSame([], OperationsConfig::problems());

        config([
            'operations.timezone' => 'Mars/Olympus',
            'operations.carry_over_days' => -1,
            'operations.shifts.morning.start' => '8am',
        ]);

        $problems = implode(' ', OperationsConfig::problems());
        $this->assertStringContainsString('OPERATIONS_TIMEZONE "Mars/Olympus"', $problems);
        $this->assertStringContainsString('OPERATIONS_CARRY_OVER_DAYS', $problems);
        $this->assertStringContainsString('Shift "morning" needs a start time', $problems);

        $this->expectException(\InvalidArgumentException::class);
        OperationsConfig::assertValid();
    }

    public function test_the_environment_check_command_passes_on_a_healthy_install(): void
    {
        $this->admin();

        $this->artisan('operations:check')
            ->expectsOutputToContain('Operations config')
            ->expectsOutputToContain('Audit log integrity')
            ->assertSuccessful();
    }

    public function test_the_environment_check_fails_without_an_application_key(): void
    {
        config(['app.key' => null]);

        $this->artisan('operations:check')->assertFailed();
    }
}
