<?php

namespace Tests\Feature\Auth;

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Models\AuditEvent;
use App\Notifications\SetPasswordNotification;
use Carbon\CarbonImmutable;
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

        Notification::assertSentTo($user, SetPasswordNotification::class);
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

        Notification::assertSentTo($user, SetPasswordNotification::class, function (SetPasswordNotification $notification) use ($user) {
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

    public function test_the_password_email_reads_correctly_for_new_accounts_and_resets(): void
    {
        $user = $this->support();
        $mail = (new SetPasswordNotification('token-123'))->toMail($user);

        $this->assertSame('Set your '.config('app.name').' password', $mail->subject);
        $this->assertSame('Choose a password', $mail->actionText);
        $this->assertStringNotContainsString('received a password reset request', implode(' ', $mail->introLines));
    }

    public function test_the_profile_counts_this_months_updates_in_the_operational_timezone(): void
    {
        config(['operations.timezone' => 'Africa/Nairobi']); // UTC+3
        $this->travelTo(CarbonImmutable::parse('2026-11-01 01:00:00', 'Africa/Nairobi'));
        $user = $this->support();
        $instance = $this->activityInstance([], '2026-10-31');

        // 22:30 UTC on 31 Oct is 01:30 on 1 Nov in Nairobi: it belongs to November there.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 22:30:00', 'UTC'));
        app(RecordActivityUpdate::class)->handle($instance, $user, ActivityStatus::Done, 'Checked just after local midnight.', 0);

        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:00:00', 'Africa/Nairobi'));
        $this->actingAs($user)->get('/profile')->assertOk()->assertViewHas('updatesThisMonth', 1);
    }
}
