<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const PROMPT = 'Password (min. 10 characters, mixed case and a number)';

    public function test_create_admin_creates_an_active_administrator_without_echoing_the_password(): void
    {
        $this->artisan('operations:create-admin', ['--name' => '  Kofi Boateng ', '--email' => 'Kofi.Boateng@Example.com'])
            ->expectsQuestion(self::PROMPT, 'Strong-Pass-2026')
            ->expectsOutputToContain('Administrator kofi.boateng@example.com created')
            ->doesntExpectOutputToContain('Strong-Pass-2026')
            ->assertSuccessful();

        $admin = User::query()->sole();
        $this->assertSame('Kofi Boateng', $admin->name);
        $this->assertSame(Role::Administrator, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Strong-Pass-2026', $admin->password));
        $this->assertDatabaseHas('audit_events', ['event' => 'personnel.created', 'subject_id' => $admin->id, 'actor_id' => null]);
    }

    public function test_create_admin_rejects_weak_passwords_and_existing_emails(): void
    {
        $this->artisan('operations:create-admin', ['--name' => 'Weak', '--email' => 'weak@example.com'])
            ->expectsQuestion(self::PROMPT, 'short')
            ->assertFailed();

        $this->support(['email' => 'taken@example.com']);

        $this->artisan('operations:create-admin', ['--name' => 'Duplicate', '--email' => 'TAKEN@example.com'])
            ->expectsQuestion(self::PROMPT, 'Strong-Pass-2026')
            ->expectsOutputToContain('already been taken')
            ->assertFailed();

        $this->assertSame(0, User::query()->where('role', Role::Administrator)->count());
    }

    public function test_verify_audit_chain_reports_intact_and_broken_chains(): void
    {
        $admin = $this->admin();
        foreach (range(1, 3) as $i) {
            app(AuditLogger::class)->record('definition.updated', "Change {$i}", actor: $admin);
        }

        $this->artisan('operations:verify-audit-chain')
            ->expectsOutputToContain('Audit chain intact: 3 event(s) verified')
            ->assertSuccessful();

        DB::table('audit_events')->where('description', 'Change 2')->update(['description' => 'Rewritten']);

        $this->artisan('operations:verify-audit-chain')
            ->expectsOutputToContain('Audit chain broken at event')
            ->assertFailed();
    }

    public function test_the_daily_generation_is_scheduled_at_one_minute_past_midnight_local_time(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('operations:generate-instances')
            ->assertSuccessful();
    }
}
