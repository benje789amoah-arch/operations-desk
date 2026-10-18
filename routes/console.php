<?php

use App\Actions\GenerateDailyInstances;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OperationsConfig;
use App\Support\OpsClock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
    // Stored emails are lower case (see User::email()), so validate them that way.
    $email = Str::lower(trim($this->option('email') ?: text('Work email', required: true)));
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

/*
|--------------------------------------------------------------------------
| Environment check
|--------------------------------------------------------------------------
|
| Run after every deployment. Exits non-zero if anything would stop the
| desk working correctly; warnings flag settings to review for production.
|
*/

Artisan::command('operations:check', function (AuditLogger $audit) {
    $production = app()->isProduction();
    $rows = [];
    $add = function (string $status, string $check, string $detail) use (&$rows) {
        $rows[] = [$status, $check, $detail];
    };

    $problems = OperationsConfig::problems();
    $add($problems ? 'FAIL' : 'OK', 'Operations config', $problems ? implode(' ', $problems) : config('operations.timezone').', '.count(config('operations.shifts')).' shifts, carry-over '.config('operations.carry_over_days').' days');
    $add(config('app.key') ? 'OK' : 'FAIL', 'Application key', config('app.key') ? 'Set' : 'Run php artisan key:generate');
    $add($production && config('app.debug') ? 'FAIL' : 'OK', 'Debug mode', config('app.debug') ? 'APP_DEBUG=true'.($production ? ' (must be false in production)' : '') : 'Off');
    $add(file_exists(public_path('build/manifest.json')) ? 'OK' : 'FAIL', 'Front-end assets', file_exists(public_path('build/manifest.json')) ? 'Built' : 'Run npm ci && npm run build');

    if ($production) {
        $add(str_starts_with((string) config('app.url'), 'https://') ? 'OK' : 'WARN', 'APP_URL uses HTTPS', (string) config('app.url'));
        $add(config('session.secure') ? 'OK' : 'WARN', 'Secure session cookie', config('session.secure') ? 'On' : 'Set SESSION_SECURE_COOKIE=true');
        $add(in_array(config('mail.default'), ['log', 'array'], true) ? 'WARN' : 'OK', 'Mail transport', config('mail.default').' (password links need a real mailer)');
    }

    try {
        DB::connection()->getPdo();
        $migrator = app('migrator');
        $pending = $migrator->repositoryExists()
            ? array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan())
            : ['all'];
        $add('OK', 'Database connection', config('database.default').' connected');
        $add($pending ? 'FAIL' : 'OK', 'Migrations', $pending ? count($pending).' pending: run php artisan migrate --force' : 'Up to date');

        if (! $pending) {
            $admins = User::query()->active()->where('role', Role::Administrator)->count();
            $add($admins ? 'OK' : 'WARN', 'Active administrator', $admins ? $admins.' found' : 'Run php artisan operations:create-admin');

            $chain = $audit->verifyChain();
            $add($chain['valid'] ? 'OK' : 'FAIL', 'Audit log integrity', $chain['valid'] ? $chain['checked'].' events verified' : "Broken at event #{$chain['broken_at']}");
        }
    } catch (Throwable $exception) {
        $add('FAIL', 'Database connection', $exception->getMessage());
    }

    $this->table(['Status', 'Check', 'Detail'], $rows);

    return collect($rows)->contains(fn ($row) => $row[0] === 'FAIL') ? 1 : 0;
})->purpose('Check configuration, database and integrity before or after deployment');

Artisan::command('operations:verify-audit-chain', function (AuditLogger $audit) {
    $result = $audit->verifyChain();

    if ($result['valid']) {
        $this->info("Audit chain intact: {$result['checked']} event(s) verified.");

        return 0;
    }

    $this->error("Audit chain broken at event #{$result['broken_at']}: {$result['reason']}");

    return 1;
})->purpose('Verify the tamper-evident hash chain of the audit log');
