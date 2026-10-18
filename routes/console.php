<?php

use App\Actions\GenerateDailyInstances;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OpsClock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
|--------------------------------------------------------------------------
| Daily instance generation
|--------------------------------------------------------------------------
|
| Safe to run any number of times: an instance is unique per definition and
| operational date. --days backfills or pre-creates a range ending at --date.
|
*/

Artisan::command('operations:generate-instances {--date= : Operational date (Y-m-d), defaults to today} {--days=1 : Number of days ending at --date}', function (GenerateDailyInstances $generator) {
    $end = OpsClock::safeDate($this->option('date'));
    $days = max(1, (int) $this->option('days'));
    $total = 0;

    foreach (OpsClock::datesBetween($end->subDays($days - 1), $end) as $date) {
        $created = $generator->handle($date);
        $total += $created;
        $this->line(sprintf('%s  %d created', $date->format('Y-m-d'), $created));
    }

    $this->info("Done. {$total} instance(s) created; existing instances were left untouched.");
})->purpose('Create the daily activity instances due on an operational date');

Schedule::command('operations:generate-instances')
    ->dailyAt('00:01')
    ->timezone(config('operations.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Administrator setup
|--------------------------------------------------------------------------
|
| The documented, secure way to create the first administrator in any
| environment. The password is typed interactively and never echoed.
|
*/

Artisan::command('operations:create-admin {--name=} {--email=}', function (AuditLogger $audit) {
    $name = $this->option('name') ?: text('Full name', required: true);
    $email = $this->option('email') ?: text('Work email', required: true);
    $secret = password('Password (min. 10 characters, mixed case and a number)', required: true);

    $validator = Validator::make(
        ['name' => $name, 'email' => $email, 'password' => $secret],
        ['name' => ['required', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', Password::defaults()]],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = (new User)->forceFill([
        'name' => $name,
        'email' => $email,
        'role' => Role::Administrator,
        'designation' => 'Administrator',
        'password' => $secret,
        'password_changed_at' => now(),
        'is_active' => true,
    ]);
    $user->save();

    $audit->recordSystem('personnel.created', "Created administrator account for {$name} from the console", $user, $email);
    $this->info("Administrator {$email} created. Sign in at ".route('login'));

    return 0;
})->purpose('Create an administrator account (interactive)');

Artisan::command('operations:verify-audit-chain', function (AuditLogger $audit) {
    $result = $audit->verifyChain();

    if ($result['valid']) {
        $this->info("Audit chain intact: {$result['checked']} event(s) verified.");

        return 0;
    }

    $this->error("Audit chain broken at event #{$result['broken_at']}: {$result['reason']}");

    return 1;
})->purpose('Verify the tamper-evident hash chain of the audit log');
