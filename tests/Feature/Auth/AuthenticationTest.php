<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in to the desk');
    }

    public function test_users_can_sign_in_and_the_sign_in_is_audited(): void
    {
        $user = $this->support(['email' => 'kwesi@example.com']);

        $this->post('/login', ['email' => 'kwesi@example.com', 'password' => 'Password-2026'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login', 'actor_id' => $user->id]);
    }

    public function test_the_email_address_is_not_case_sensitive_when_signing_in(): void
    {
        $user = $this->support(['email' => 'kwesi@example.com']);

        $this->post('/login', ['email' => ' Kwesi@Example.COM ', 'password' => 'Password-2026'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_with_a_clear_message(): void
    {
        $this->support(['email' => 'kwesi@example.com']);

        $this->from('/login')->post('/login', ['email' => 'kwesi@example.com', 'password' => 'not-the-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Those details do not match an active account. Check your email and password.']);

        $this->assertGuest();
    }

    public function test_failed_attempts_never_store_the_attempted_password(): void
    {
        $this->support(['email' => 'kwesi@example.com']);

        $this->post('/login', ['email' => 'kwesi@example.com', 'password' => 'Secret-Guess-123']);

        $event = AuditEvent::query()->where('event', 'auth.login_failed')->firstOrFail();
        $this->assertSame('kwesi@example.com', $event->subject_label);
        $this->assertStringNotContainsString('Secret-Guess-123', json_encode($event->getAttributes()));
    }

    public function test_deactivated_users_cannot_sign_in_even_with_the_correct_password(): void
    {
        User::factory()->inactive()->create(['email' => 'left@example.com']);

        $this->post('/login', ['email' => 'left@example.com', 'password' => 'Password-2026'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact an administrator if you still need access.']);

        $this->assertGuest();
    }

    public function test_sign_in_is_rate_limited_after_repeated_failures(): void
    {
        $this->support(['email' => 'kwesi@example.com']);

        foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->post('/login', ['email' => 'kwesi@example.com', 'password' => 'wrong-'.$attempt]);
        }

        $this->post('/login', ['email' => 'kwesi@example.com', 'password' => 'Password-2026'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.locked_out']);
    }

    public function test_users_can_sign_out(): void
    {
        $this->actingAs($this->support())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public static function protectedPages(): array
    {
        return [
            ['/'], ['/daily-register'], ['/definitions'], ['/handover'], ['/handover/print'],
            ['/reports'], ['/reports/daily'], ['/personnel'], ['/audit-log'], ['/profile'], ['/profile/password'],
        ];
    }

    #[DataProvider('protectedPages')]
    public function test_guests_are_redirected_to_sign_in(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@example.com'])->assertStatus(404);
    }

    public function test_an_account_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->support();
        $this->actingAs($user)->get('/')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
