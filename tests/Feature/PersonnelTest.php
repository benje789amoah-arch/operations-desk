<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Personnel\PersonnelForm;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_creates_an_account_and_the_person_sets_their_own_password(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin())
            ->test(PersonnelForm::class)
            ->set('name', 'Yaw Darko')
            ->set('email', 'yaw.darko@example.com')
            ->set('staff_number', 'NP-921')
            ->set('role', 'support')
            ->set('designation', 'Applications Support Analyst')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $user = User::query()->where('email', 'yaw.darko@example.com')->sole();
        $this->assertSame(Role::Support, $user->role);
        $this->assertTrue($user->is_active);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('audit_events', ['event' => 'personnel.created', 'subject_id' => $user->id]);
    }

    public function test_emails_and_staff_numbers_must_be_unique(): void
    {
        $this->support(['email' => 'taken@example.com', 'staff_number' => 'NP-100']);

        Livewire::actingAs($this->admin())
            ->test(PersonnelForm::class)
            ->set('name', 'Duplicate Person')
            ->set('email', 'taken@example.com')
            ->set('staff_number', 'NP-100')
            ->call('save')
            ->assertHasErrors(['email' => 'unique', 'staff_number' => 'unique']);
    }

    public function test_role_changes_are_audited_with_before_and_after_values(): void
    {
        $person = $this->support();

        Livewire::actingAs($this->admin())
            ->test(PersonnelForm::class, ['user' => $person])
            ->set('role', 'supervisor')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Role::Supervisor, $person->fresh()->role);
        $this->assertDatabaseHas('audit_events', ['event' => 'personnel.role_changed', 'subject_id' => $person->id]);
    }

    public function test_deactivating_keeps_records_and_blocks_access(): void
    {
        $admin = $this->admin();
        $person = $this->support();

        Livewire::actingAs($admin)
            ->test(PersonnelForm::class, ['user' => $person])
            ->call('setActive', false)
            ->assertHasNoErrors();

        $this->assertFalse($person->fresh()->is_active);
        $this->assertNotNull(User::find($person->id));
        $this->post('/logout');
        $this->post('/login', ['email' => $person->email, 'password' => 'Password-2026'])->assertSessionHasErrors('email');
    }

    public function test_administrators_cannot_deactivate_themselves_or_remove_the_last_administrator(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(PersonnelForm::class, ['user' => $admin])
            ->call('setActive', false)
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test(PersonnelForm::class, ['user' => $admin])
            ->set('role', 'support')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdministrator());
    }
}
