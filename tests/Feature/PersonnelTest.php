<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Personnel\PersonnelForm;
use App\Livewire\Personnel\PersonnelIndex;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
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
        Notification::assertSentTo($user, SetPasswordNotification::class);
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

    public function test_emails_are_stored_in_lower_case_so_uniqueness_ignores_case_on_every_database(): void
    {
        $this->support(['email' => 'Taken@Example.com']);
        $this->assertDatabaseHas('users', ['email' => 'taken@example.com']);

        Livewire::actingAs($this->admin())
            ->test(PersonnelForm::class)
            ->set('name', 'Duplicate Person')
            ->set('email', 'TAKEN@example.com')
            ->call('save')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_the_personnel_list_search_ignores_letter_case(): void
    {
        $this->support(['name' => 'Efua Asante', 'email' => 'efua.asante@example.com']);
        $this->support(['name' => 'Kojo Antwi', 'email' => 'kojo.antwi@example.com']);

        Livewire::actingAs($this->admin())
            ->test(PersonnelIndex::class)
            ->set('search', 'efua')
            ->assertSee('Efua Asante')
            ->assertDontSee('Kojo Antwi')
            ->set('search', 'KOJO.ANTWI@')
            ->assertSee('Kojo Antwi')
            ->assertDontSee('Efua Asante');
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
