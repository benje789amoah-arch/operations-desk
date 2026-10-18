<?php

namespace Tests\Feature;

use App\Livewire\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function seedEvents(int $count = 5): void
    {
        $admin = $this->admin();

        foreach (range(1, $count) as $i) {
            app(AuditLogger::class)->record('definition.updated', "Change number {$i}", null, "ACT-00{$i}", ['before' => ['title' => "Old {$i}"], 'after' => ['title' => "New {$i}"]], $admin);
        }
    }

    public function test_each_event_is_chained_to_the_previous_one(): void
    {
        $this->seedEvents(3);

        $events = AuditEvent::query()->orderBy('id')->get();
        $this->assertNull($events[0]->previous_hash);
        $this->assertSame($events[0]->hash, $events[1]->previous_hash);
        $this->assertSame($events[1]->hash, $events[2]->previous_hash);
        $this->assertTrue(app(AuditLogger::class)->verifyChain()['valid']);
    }

    public function test_editing_an_event_in_the_database_is_detected(): void
    {
        $this->seedEvents(4);
        $target = AuditEvent::query()->orderBy('id')->skip(2)->first();

        DB::table('audit_events')->where('id', $target->id)->update(['description' => 'Quietly rewritten']);

        $result = app(AuditLogger::class)->verifyChain();
        $this->assertFalse($result['valid']);
        $this->assertSame($target->id, $result['broken_at']);
    }

    public function test_deleting_an_event_in_the_database_is_detected(): void
    {
        $this->seedEvents(4);
        $events = AuditEvent::query()->orderBy('id')->get();

        DB::table('audit_events')->where('id', $events[1]->id)->delete();

        $result = app(AuditLogger::class)->verifyChain();
        $this->assertFalse($result['valid']);
        $this->assertSame($events[2]->id, $result['broken_at']);
    }

    public function test_events_cannot_be_changed_through_the_application(): void
    {
        $this->seedEvents(1);
        $event = AuditEvent::sole();

        $this->expectException(LogicException::class);
        $event->update(['description' => 'Changed']);
    }

    public function test_administrators_can_filter_inspect_and_verify_the_log(): void
    {
        $this->freezeAt('2026-10-14 10:00:00');
        $this->seedEvents(3);

        Livewire::actingAs($this->admin())
            ->test(AuditLog::class)
            ->assertSee('Change number 3')
            ->set('search', 'number 2')
            ->assertSee('Change number 2')
            ->assertDontSee('Change number 3')
            ->call('inspect', AuditEvent::query()->where('description', 'Change number 2')->value('id'))
            ->assertSee('Old 2')
            ->assertSee('New 2')
            ->call('verifyChain')
            ->assertSee('Audit chain intact');
    }

    public function test_the_audit_export_is_restricted_and_produces_csv(): void
    {
        $this->seedEvents(2);

        $this->actingAs($this->supervisor())->get(route('audit.export'))->assertForbidden();

        $csv = $this->actingAs($this->admin())->get(route('audit.export', ['from' => now()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]))->streamedContent();
        $this->assertStringContainsString('Change number 1', $csv);
    }
}
