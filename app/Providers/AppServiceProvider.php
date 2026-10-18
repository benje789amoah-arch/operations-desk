<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Ability;
use App\Support\OperationsConfig;
use App\View\Composers\AppShellComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OperationsConfig::assertValid();

        $this->registerAbilities();
        $this->registerAuthenticationAuditing();

        // Surface N+1 queries and mass-assignment mistakes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(10)->letters()->mixedCase()->numbers());

        Paginator::defaultView('pagination.default');

        View::composer('components.layouts.app', AppShellComposer::class);

        // Livewire re-applies these on every component request made from an
        // open page, so a deactivated account or a password change elsewhere
        // takes effect immediately, not only on the next full page load.
        Livewire::addPersistentMiddleware([
            AuthenticateSession::class,
            EnsureUserIsActive::class,
        ]);

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Every ability in the permission matrix becomes a gate. Deactivated
     * accounts are denied everything, even if a session somehow survives.
     */
    private function registerAbilities(): void
    {
        foreach (Ability::matrix() as $ability => $definition) {
            Gate::define($ability, fn (User $user) => $user->is_active && $user->hasRole(...$definition['roles']));
        }
    }

    private function registerAuthenticationAuditing(): void
    {
        Event::listen(function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            app(AuditLogger::class)->record('auth.login', 'Signed in', $event->user, $event->user->email, actor: $event->user);
        });

        Event::listen(function (Logout $event) {
            if ($event->user instanceof User) {
                app(AuditLogger::class)->record('auth.logout', 'Signed out', $event->user, $event->user->email, actor: $event->user);
            }
        });

        Event::listen(function (Failed $event) {
            // Never record the attempted password; the email is enough to investigate.
            app(AuditLogger::class)->recordSystem('auth.login_failed', 'Failed sign-in attempt', null, (string) ($event->credentials['email'] ?? 'unknown'));
        });

        Event::listen(function (Lockout $event) {
            app(AuditLogger::class)->recordSystem('auth.locked_out', 'Too many sign-in attempts; temporarily locked', null, (string) $event->request->input('email'));
        });

        Event::listen(function (PasswordReset $event) {
            app(AuditLogger::class)->record('auth.password_reset', 'Password reset using an emailed link', $event->user, $event->user->email, actor: $event->user);
        });
    }
}
