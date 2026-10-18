<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OperationsConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Run after every deployment. Exits non-zero if anything would stop the desk
 * working correctly; WARN rows flag settings to review for production.
 */
class CheckInstallation extends Command
{
    protected $signature = 'operations:check';

    protected $description = 'Check configuration, database and integrity before or after deployment';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(AuditLogger $audit): int
    {
        $this->checkConfiguration();

        if ($this->laravel->isProduction()) {
            $this->checkProductionSettings();
        }

        $this->checkDatabase($audit);

        $this->table(['Status', 'Check', 'Detail'], $this->rows);

        return collect($this->rows)->contains(fn (array $row) => $row[0] === 'FAIL') ? self::FAILURE : self::SUCCESS;
    }

    private function checkConfiguration(): void
    {
        $problems = OperationsConfig::problems();
        $this->report(! $problems, 'Operations config', $problems
            ? implode(' ', $problems)
            : sprintf('%s, %d shifts, carry-over %d days', config('operations.timezone'), count(config('operations.shifts')), config('operations.carry_over_days')));

        $this->report((bool) config('app.key'), 'Application key', config('app.key') ? 'Set' : 'Run php artisan key:generate');

        $debugInProduction = $this->laravel->isProduction() && config('app.debug');
        $this->report(! $debugInProduction, 'Debug mode', config('app.debug') ? 'APP_DEBUG=true'.($debugInProduction ? ' (must be false in production)' : '') : 'Off');

        $built = file_exists(public_path('build/manifest.json'));
        $this->report($built, 'Front-end assets', $built ? 'Built' : 'Run npm ci && npm run build');
    }

    private function checkProductionSettings(): void
    {
        $this->report(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS', (string) config('app.url'), warnOnly: true);
        $this->report((bool) config('session.secure'), 'Secure session cookie', config('session.secure') ? 'On' : 'Set SESSION_SECURE_COOKIE=true', warnOnly: true);
        $this->report(! in_array(config('mail.default'), ['log', 'array'], true), 'Mail transport', config('mail.default').' (password links need a real mailer)', warnOnly: true);
    }

    private function checkDatabase(AuditLogger $audit): void
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->report(false, 'Database connection', $exception->getMessage());

            return;
        }

        $this->report(true, 'Database connection', config('database.default').' connected');

        $migrator = $this->laravel->make('migrator');
        $pending = $migrator->repositoryExists()
            ? array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan())
            : ['all'];
        $this->report(! $pending, 'Migrations', $pending ? count($pending).' pending: run php artisan migrate --force' : 'Up to date');

        if ($pending) {
            return;
        }

        $admins = User::query()->active()->where('role', Role::Administrator)->count();
        $this->report($admins > 0, 'Active administrator', $admins ? $admins.' found' : 'Run php artisan operations:create-admin', warnOnly: true);

        $chain = $audit->verifyChain();
        $this->report($chain['valid'], 'Audit log integrity', $chain['valid'] ? $chain['checked'].' events verified' : "Broken at event #{$chain['broken_at']}");
    }

    private function report(bool $passed, string $check, string $detail, bool $warnOnly = false): void
    {
        $this->rows[] = [$passed ? 'OK' : ($warnOnly ? 'WARN' : 'FAIL'), $check, $detail];
    }
}
