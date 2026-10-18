<?php

namespace Database\Seeders;

use App\Actions\AcknowledgeHandover;
use App\Actions\GenerateDailyInstances;
use App\Actions\Personnel\SetPersonnelActive;
use App\Actions\RecordActivityUpdate;
use App\Actions\SaveActivityDefinition;
use App\Actions\ScheduleActivityInstance;
use App\Actions\SubmitHandover;
use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Enums\Recurrence;
use App\Enums\Role;
use App\Models\ActivityDefinition;
use App\Models\ActivityInstance;
use App\Models\Category;
use App\Models\Handover;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEMONSTRATION DATA — for local development and assessment only.
 *
 * People, remarks and figures below are fictional. They do not describe real
 * company operations, and the application has not verified any real service,
 * log or transaction.
 *
 * History is replayed chronologically through the real business actions
 * (instance generation, RecordActivityUpdate, handovers) with the clock set
 * to each event's time, so versions, latest-update pointers, timestamps and
 * the audit hash chain are exactly what live use would have produced.
 *
 * All demo accounts use the password in DEMO_PASSWORD.
 */
class DemoDataSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Desk-Demo-2026';

    private const HISTORY_DAYS = 20;

    /** @var array<string, User> */
    private array $people = [];

    /** @var array<string, ActivityDefinition> */
    private array $definitions = [];

    /** @var array<int, array{at: CarbonImmutable, run: callable}> */
    private array $timeline = [];

    public function run(): void
    {
        mt_srand(20261018);

        $realNow = CarbonImmutable::now();
        $today = OpsClock::today();
        $start = $today->subDays(self::HISTORY_DAYS);

        try {
            $this->travelTo($start->subDay()->setTime(9, 0));
            $this->createPeople();
            $this->createDefinitions($start);

            foreach (OpsClock::datesBetween($start, $today) as $date) {
                $this->planDay($date, $today);
            }

            $this->planDeactivation($start->addDays(9));
            $this->replay($realNow);
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        $this->command?->info('Demo data seeded. Sign in with any demo account (see README) using the password '.self::DEMO_PASSWORD);
    }

    private function createPeople(): void
    {
        $audit = app(AuditLogger::class);

        $people = [
            'admin' => ['Kofi Boateng', 'kofi.boateng@example.com', 'NP-491', Role::Administrator, 'Platform Administrator', 'Platform Engineering'],
            'ama' => ['Ama Mensah', 'ama.mensah@example.com', 'NP-804', Role::Supervisor, 'Senior Operations Analyst', 'Applications Support'],
            'abena' => ['Abena Kyei', 'abena.kyei@example.com', 'NP-388', Role::Supervisor, 'Support Supervisor (Evening)', 'Applications Support'],
            'kwesi' => ['Kwesi Appiah', 'kwesi.appiah@example.com', 'NP-712', Role::Support, 'Applications Support Analyst', 'Applications Support'],
            'yaw' => ['Yaw Darko', 'yaw.darko@example.com', 'NP-921', Role::Support, 'Applications Support Analyst', 'Applications Support'],
            'emmanuel' => ['Emmanuel Osei', 'emmanuel.osei@example.com', 'NP-655', Role::Support, 'Night Operations Analyst', 'Applications Support'],
            'esi' => ['Esi Owusu', 'esi.owusu@example.com', 'NP-233', Role::Support, 'Applications Support Analyst', 'Applications Support'],
        ];

        foreach ($people as $key => [$name, $email, $staff, $role, $designation, $team]) {
            $user = User::query()->where('email', $email)->first() ?? (new User)->forceFill([
                'name' => $name,
                'email' => $email,
                'staff_number' => $staff,
                'role' => $role,
                'designation' => $designation,
                'team' => $team,
                'is_active' => true,
                'password' => self::DEMO_PASSWORD,
                'password_changed_at' => now(),
            ]);

            if (! $user->exists) {
                $user->save();
                $audit->record('personnel.created', "Created personnel account for {$name} (demo data)", $user, $staff, ['after' => ['role' => $role->value]], $this->people['admin'] ?? $user);
            }

            $this->people[$key] = $user;
        }
    }

    private function createDefinitions(CarbonImmutable $start): void
    {
        $save = app(SaveActivityDefinition::class);
        $category = fn (string $name) => Category::query()->where('name', $name)->value('id');

        $definitions = [
            'sms' => ['Daily SMS count in comparison to SMS count from logs', 'Messaging & Telco', Priority::Critical, Recurrence::Daily, null, '08:00', '09:30', 'ama',
                "1. Run the daily SMS count query against the application database for the previous day.\n2. Extract the delivered-SMS count from the aggregator gateway logs for the same window.\n3. Compare the two figures. Record both counts in Supporting details.\n4. Any difference above 0.05% is Pending and must be raised with the aggregator before handover."],
            'errors' => ['Review of application error logs', 'Application Health', Priority::High, Recurrence::Daily, null, '08:15', '10:00', 'kwesi',
                'Review the last 24 hours of application error logs. Note new exception types, spikes compared with the previous day and any errors affecting customer journeys. Raise a ticket for anything new and reference it in the remark.'],
            'jobs' => ['Verification of scheduled application jobs', 'Scheduled Jobs & Queues', Priority::Normal, Recurrence::Daily, null, '08:30', '10:00', 'yaw',
                'Confirm every scheduled job ran at its expected time and completed successfully. Check the queue workers are running and the failed-jobs table has no new entries. Re-run failed jobs only after confirming the cause.'],
            'transactions' => ['Review of failed transaction records', 'Payments & Transactions', Priority::High, Recurrence::Daily, null, '09:00', '11:00', 'ama',
                'List transactions that failed or are stuck in processing from the previous day. Confirm each has been reversed, retried or escalated. Record counts and any merchant affected.'],
            'availability' => ['Verification of service availability', 'Service Availability', Priority::High, Recurrence::Daily, null, '07:45', '08:30', 'emmanuel',
                'Check customer-facing endpoints, USSD channels and partner APIs respond normally. Note any slow or failing endpoint with the time observed.'],
            'incidents' => ['Follow-up on outstanding application incidents', 'Incident Management', Priority::Normal, Recurrence::Weekdays, null, '10:00', '16:00', 'kwesi',
                'Review every open incident ticket. Update the ticket owner on progress, chase blocked items and record which incidents remain open at the end of the check.'],
            'backups' => ['Database backup completion check', 'Infrastructure', Priority::Normal, Recurrence::Daily, null, '07:30', '09:00', 'emmanuel',
                "Confirm last night's database backups completed, are the expected size and were copied to secondary storage."],
            'ssl' => ['SSL certificate expiry review', 'Security', Priority::Normal, Recurrence::Weekly, [1], '11:00', null, 'yaw',
                'Review certificates for public endpoints. Anything expiring within 30 days must be raised with Platform Engineering.'],
            'monthend' => ['Month-end settlement report sign-off', 'Payments & Transactions', Priority::High, Recurrence::Monthly, [1], '12:00', '17:00', 'ama',
                'Reconcile the month-end settlement report against transaction records and obtain supervisor sign-off.'],
            'release' => ['Post-release smoke test', 'Application Health', Priority::High, Recurrence::AdHoc, null, '14:00', '16:00', null,
                'After a production release, run the smoke test checklist on login, payments and messaging, and confirm no new errors in the logs for 30 minutes.'],
        ];

        $offset = 0;
        foreach ($definitions as $key => [$title, $categoryName, $priority, $recurrence, $days, $scheduled, $due, $assignee, $description]) {
            $existing = ActivityDefinition::query()->where('title', $title)->first();

            $this->travelTo($start->subDay()->setTime(10, 0)->addMinutes($offset += 3));
            $this->definitions[$key] = $existing ?? $save->handle(null, [
                'title' => $title,
                'description' => $description,
                'category_id' => $category($categoryName),
                'priority' => $priority,
                'recurrence' => $recurrence,
                'recurrence_days' => $days,
                'scheduled_time' => $scheduled,
                'due_time' => $due,
                'default_assignee_id' => $assignee ? $this->people[$assignee]->id : null,
                'starts_on' => $start->format('Y-m-d'),
                'ends_on' => null,
            ], $this->people['admin']);
        }
    }

    /**
     * Plan the instance generation, updates and handovers for one day.
     */
    private function planDay(CarbonImmutable $date, CarbonImmutable $today): void
    {
        $isToday = $date->isSameDay($today);
        $daysAgo = (int) $date->diffInDays($today);

        $this->at($date->setTime(0, 1), fn () => app(GenerateDailyInstances::class)->handle($date));

        if ($daysAgo === 6) {
            $this->at($date->setTime(13, 10), fn () => app(ScheduleActivityInstance::class)->handle($this->definitions['release'], $date->format('Y-m-d'), $this->people['yaw'], $this->people['ama']));
        }

        // Esi Owusu leaves the team (account deactivated) eleven days ago.
        $onDuty = $daysAgo >= 11 ? ['ama', 'kwesi', 'yaw', 'esi', 'emmanuel'] : ['ama', 'kwesi', 'yaw', 'abena', 'emmanuel'];

        foreach ($this->definitions as $key => $definition) {
            $applies = $definition->appliesOn($date) || ($key === 'release' && $daysAgo === 6);
            if (! $applies) {
                continue;
            }

            $base = $date->setTimeFromTimeString($definition->scheduled_time ?? '09:00')->addMinutes(mt_rand(5, 40));

            // Usually the default assignee does the check; sometimes a colleague covers.
            $assigneeKey = collect($this->people)->search(fn (User $u) => $u->id === $definition->default_assignee_id);
            $personKey = ($assigneeKey && in_array($assigneeKey, $onDuty, true) && mt_rand(1, 100) <= 75)
                ? $assigneeKey
                : $onDuty[array_rand($onDuty)];

            $this->planOutcome($key, $date, $base, $this->people[$personKey], $isToday, $daysAgo);
        }

        if (! $isToday && $daysAgo <= 7) {
            $this->planHandover($date, 'morning', 'ama', 'abena', $daysAgo !== 1);
        }
    }

    private function planOutcome(string $key, CarbonImmutable $date, CarbonImmutable $at, User $person, bool $isToday, int $daysAgo): void
    {
        $roll = mt_rand(1, 100);
        $remarks = $this->remarks($key);

        // Today: a realistic part-way picture depending on the time of day.
        if ($isToday) {
            if ($roll <= 55) {
                $this->update($date, $key, $at, $person, ActivityStatus::Done, $remarks['done'][array_rand($remarks['done'])], $remarks['details'] ?? null);
            } elseif ($roll <= 80) {
                $this->update($date, $key, $at, $person, ActivityStatus::Pending, $remarks['pending'][array_rand($remarks['pending'])], $remarks['details'] ?? null, $key === 'sms');
            }

            return; // the rest stay "not updated" for now
        }

        if ($daysAgo === 1 && in_array($key, ['sms', 'incidents'], true)) {
            // Still unresolved: carried over into today's handover.
            $this->update($date, $key, $at, $person, ActivityStatus::Pending, $remarks['pending'][0], $remarks['details'] ?? null, true);
            $this->update($date, $key, $at->addHours(4), $this->people['abena'], ActivityStatus::Pending, $remarks['ongoing'][0], null, true);

            return;
        }

        if ($roll <= 4 && $daysAgo > 3) {
            return; // never updated: shows as "not updated at close" in reports
        }

        if ($roll <= 72) {
            $this->update($date, $key, $at, $person, ActivityStatus::Done, $remarks['done'][array_rand($remarks['done'])], $remarks['details'] ?? null);
        } elseif ($roll <= 90) {
            $this->update($date, $key, $at, $person, ActivityStatus::Pending, $remarks['pending'][array_rand($remarks['pending'])], $remarks['details'] ?? null, mt_rand(0, 1) === 1);
            $resolvedNextDay = $roll > 86;
            $resolver = $resolvedNextDay ? $this->people['ama'] : $person;
            $this->update($date, $key, $resolvedNextDay ? $date->addDay()->setTime(9, mt_rand(10, 50)) : $at->addHours(mt_rand(2, 5)), $resolver, ActivityStatus::Done, $remarks['resolved'][array_rand($remarks['resolved'])]);
        } else {
            $this->update($date, $key, $at, $person, ActivityStatus::Done, $remarks['done'][array_rand($remarks['done'])], $remarks['details'] ?? null);
            $this->update($date, $key, $at->addHours(2), $this->people['ama'], ActivityStatus::Pending, $remarks['reopen'][0], null, true);
            $this->update($date, $key, $at->addHours(5), $person, ActivityStatus::Done, $remarks['resolved'][array_rand($remarks['resolved'])]);
        }
    }

    private function update(CarbonImmutable $date, string $key, CarbonImmutable $at, User $user, ActivityStatus $status, string $remark, ?string $details = null, bool $followUp = false): void
    {
        $this->at($at, function () use ($date, $key, $user, $status, $remark, $details, $followUp) {
            $instance = ActivityInstance::query()->where('activity_definition_id', $this->definitions[$key]->id)->onDate($date)->first();

            if (! $instance || ! $user->fresh()->is_active) {
                return;
            }

            app(RecordActivityUpdate::class)->handle($instance, $user, $status, $remark, $instance->version, $details, $followUp);
        });
    }

    private function planHandover(CarbonImmutable $date, string $shift, string $outgoing, string $incoming, bool $acknowledged): void
    {
        $notes = [
            'Morning checks complete apart from the items listed as outstanding. Aggregator contact has the SMS variance ticket; please chase before 18:00.',
            'All scheduled jobs verified. One failed transaction batch was retried and completed. Nothing else outstanding beyond the list below.',
            'Quiet shift. Incident follow-ups updated on every ticket; two remain with the vendor. Watch the error log for repeat timeouts on the payments API.',
        ];

        $this->at($date->setTime(16, 5), function () use ($date, $shift, $outgoing, $notes) {
            if (! Handover::query()->where('operational_date', $date->format('Y-m-d'))->where('shift', $shift)->exists()) {
                app(SubmitHandover::class)->handle($date->format('Y-m-d'), $shift, $notes[array_rand($notes)], $this->people[$outgoing]);
            }
        });

        if ($acknowledged) {
            $this->at($date->setTime(16, 22), function () use ($date, $shift, $incoming) {
                $handover = Handover::query()->where('operational_date', $date->format('Y-m-d'))->where('shift', $shift)->first();
                if ($handover && ! $handover->isAcknowledged()) {
                    app(AcknowledgeHandover::class)->handle($handover, $this->people[$incoming], 'Reviewed the outstanding items with the outgoing lead.');
                }
            });
        }
    }

    private function planDeactivation(CarbonImmutable $date): void
    {
        $this->at($date->setTime(17, 30), fn () => app(SetPersonnelActive::class)->handle($this->people['esi'], false, $this->people['admin']));
    }

    /**
     * @param  callable(): mixed  $run
     */
    private function at(CarbonImmutable $localTime, callable $run): void
    {
        $this->timeline[] = ['at' => $localTime->utc(), 'seq' => count($this->timeline), 'run' => $run];
    }

    private function replay(CarbonImmutable $realNow): void
    {
        usort($this->timeline, fn ($a, $b) => [$a['at'], $a['seq']] <=> [$b['at'], $b['seq']]);

        foreach ($this->timeline as $event) {
            if ($event['at']->greaterThan($realNow)) {
                continue; // never create records "in the future"
            }

            $this->travelTo($event['at']);
            ($event['run'])();
        }
    }

    private function travelTo(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    /**
     * @return array<string, mixed>
     */
    private function remarks(string $key): array
    {
        $common = [
            'resolved' => [
                'Follow-up complete. Root cause confirmed and the figures now reconcile; closing this check.',
                'Issue cleared after the retry. Verified again and no further action is needed.',
                'Vendor confirmed the fix on their side. Re-checked and everything is back to normal.',
            ],
            'reopen' => ['Reopening: a later check found the earlier result was based on an incomplete extract. Re-running the verification.'],
            'ongoing' => ['Investigation is ongoing. The discrepancy has not yet been resolved; vendor has acknowledged the ticket.'],
        ];

        return $common + match ($key) {
            'sms' => [
                'done' => [
                    'SMS count from application records matched the count extracted from the logs.',
                    'Application and gateway log counts matched for the full day window. No variance.',
                ],
                'pending' => [
                    'Difference identified between the application count and log count. Further investigation is required.',
                    'Gateway log extract incomplete for the 02:00–03:00 window; cannot confirm the count yet. Re-extract requested.',
                ],
                'details' => 'App count vs log count recorded in the reconciliation sheet',
            ],
            'errors' => [
                'done' => [
                    'No new exception types. Error volume in line with the previous day.',
                    'Two known timeout warnings on the notification service; no customer impact. Nothing new to raise.',
                ],
                'pending' => [
                    'New NullReference exception on the statement download endpoint since 06:40. Ticket raised with the dev team.',
                ],
            ],
            'jobs' => [
                'done' => [
                    'All scheduled jobs ran on time and completed. Queue workers healthy, no new failed jobs.',
                    'Nightly jobs completed. One job ran 6 minutes late but finished successfully.',
                ],
                'pending' => [
                    'Interest accrual job failed at 01:15 with a lock timeout. Waiting on DBA confirmation before re-running.',
                ],
            ],
            'transactions' => [
                'done' => [
                    'Failed transactions reviewed; all reversed or retried successfully. No merchant escalations needed.',
                    'Three failed card transactions found; all auto-reversed. Confirmed with the payments dashboard.',
                ],
                'pending' => [
                    'Batch of failed transfers stuck in processing for one merchant. Escalated to the payments partner.',
                ],
                'details' => 'Failed: see transaction review sheet for counts',
            ],
            'availability' => [
                'done' => [
                    'All monitored endpoints and USSD channels responding normally.',
                    'Services available. Partner API slower than usual (around 1.2s) but within tolerance.',
                ],
                'pending' => [
                    'Partner balance API returning intermittent 503s between 07:50 and 08:05. Monitoring and logged with the partner.',
                ],
            ],
            'incidents' => [
                'done' => [
                    'All open incidents reviewed and owners updated. None blocked.',
                    'Incident follow-ups complete; one ticket closed after customer confirmation.',
                ],
                'pending' => [
                    'Awaiting customer confirmation on the refund incident before it can be closed.',
                ],
            ],
            'backups' => [
                'done' => ['Backups completed and copied to secondary storage. Sizes consistent with the previous night.'],
                'pending' => ['Secondary copy of the reporting database backup has not completed. Storage team informed.'],
            ],
            'ssl' => [
                'done' => ['No public certificates expire within 30 days.'],
                'pending' => ['Certificate for the partner callback domain expires in 21 days. Renewal request raised.'],
            ],
            'monthend' => [
                'done' => ['Settlement report reconciled and signed off.'],
                'pending' => ['Settlement report has two unmatched entries; waiting on the bank statement.'],
            ],
            'release' => [
                'done' => ['Smoke test passed for login, payments and messaging. No new errors in 30 minutes after release.'],
                'pending' => ['Smoke test found slow login on Android app; release team investigating.'],
            ],
            default => ['done' => ['Check completed.'], 'pending' => ['Check incomplete; see ticket.']],
        };
    }
}
