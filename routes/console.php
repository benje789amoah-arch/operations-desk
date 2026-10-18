<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| The commands themselves live in app/Console/Commands:
|   operations:generate-instances   create the day's activity instances
|   operations:create-admin         create an administrator (interactive)
|   operations:check                verify configuration, database, integrity
|   operations:verify-audit-chain   verify the audit log hash chain
|
| Generation is idempotent, so overlapping or repeated runs are harmless.
|
*/

Schedule::command('operations:generate-instances')
    ->dailyAt('00:01')
    ->timezone(config('operations.timezone'))
    ->withoutOverlapping()
    ->onOneServer();
