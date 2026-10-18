<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = $this->support();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_addresses_get_the_same_response_and_no_email(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_the_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = $this->support();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Brand-New-Pass-1',
                'password_confirmation' => 'Brand-New-Pass-1',
            ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('Brand-New-Pass-1', $user->fresh()->password));
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.password_reset', 'subject_id' => $user->id]);
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = $this->support();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'Password-2026',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $user = $this->support();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong',
            'password' => 'Another-Pass-2026',
            'password_confirmation' => 'Another-Pass-2026',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'Your current password is incorrect.']);

        $this->assertTrue(Hash::check('Password-2026', $user->fresh()->password));
    }

    public function test_users_can_change_their_password_and_no_secret_is_audited(): void
    {
        $user = $this->support();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'Password-2026',
            'password' => 'Another-Pass-2026',
            'password_confirmation' => 'Another-Pass-2026',
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertTrue(Hash::check('Another-Pass-2026', $user->password));
        $this->assertNotNull($user->password_changed_at);

        $event = AuditEvent::query()->where('event', 'auth.password_changed')->firstOrFail();
        $this->assertStringNotContainsString('Another-Pass-2026', json_encode($event->getAttributes()));
        $this->assertStringNotContainsString($user->password, json_encode($event->getAttributes()));
    }

    public function test_profile_never_exposes_the_password_hash(): void
    {
        $user = $this->support();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee($user->name)
            ->assertDontSee($user->password);

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }
}
