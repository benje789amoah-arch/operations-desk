<?php

use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\HandoverPrintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Activities\DailyRegister;
use App\Livewire\Activities\InstanceDetail;
use App\Livewire\Audit\AuditLog;
use App\Livewire\Dashboard;
use App\Livewire\Definitions\DefinitionForm;
use App\Livewire\Definitions\DefinitionIndex;
use App\Livewire\Definitions\DefinitionShow;
use App\Livewire\Handover\HandoverCentre;
use App\Livewire\Personnel\PersonnelForm;
use App\Livewire\Personnel\PersonnelIndex;
use App\Livewire\Reports\ReportCentre;
use App\Livewire\Reports\ReportIndex;
use App\Models\ActivityDefinition;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Support\Ability;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
| Public registration is intentionally absent: administrators create
| personnel accounts, and new users set their password via an emailed link.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated desk
|--------------------------------------------------------------------------
| Every route requires a signed-in, active account. Role permissions are
| enforced again inside components and actions, not only here.
*/

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/daily-register', DailyRegister::class)->name('register');
    Route::get('/activities/{instance}', InstanceDetail::class)->name('activities.show');

    Route::get('/definitions', DefinitionIndex::class)->name('definitions.index');
    Route::get('/definitions/create', DefinitionForm::class)->can('create', ActivityDefinition::class)->name('definitions.create');
    Route::get('/definitions/{definition}', DefinitionShow::class)->name('definitions.show');
    Route::get('/definitions/{definition}/edit', DefinitionForm::class)->can('update', 'definition')->name('definitions.edit');

    Route::get('/handover', HandoverCentre::class)->name('handover.index');
    Route::get('/handover/print', HandoverPrintController::class)->name('handover.print');

    Route::get('/reports', ReportIndex::class)->name('reports.index');
    Route::get('/reports/{type}', ReportCentre::class)->whereIn('type', array_keys(ReportRegistry::all()))->name('reports.show');
    Route::get('/reports/{type}/export', ReportExportController::class)
        ->whereIn('type', array_keys(ReportRegistry::all()))
        ->middleware('throttle:30,1')
        ->name('reports.export');

    Route::middleware('can:'.Ability::MANAGE_PERSONNEL)->group(function () {
        Route::get('/personnel', PersonnelIndex::class)->name('personnel.index');
        Route::get('/personnel/create', PersonnelForm::class)->can('create', User::class)->name('personnel.create');
        Route::get('/personnel/{user}', PersonnelForm::class)->can('update', 'user')->name('personnel.edit');
    });

    Route::middleware('can:'.Ability::VIEW_AUDIT_LOG)->group(function () {
        Route::get('/audit-log', AuditLog::class)->name('audit.index');
        Route::get('/audit-log/export', AuditExportController::class)->middleware('throttle:10,1')->name('audit.export');
    });

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('password.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
});
