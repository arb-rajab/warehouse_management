<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });

        // Auth::check()/Auth::id() force a fresh guard resolution when no user is cached
        // yet, which queries the users table; Telescope then tries to tag *that* query,
        // re-entering this closure before the original lookup has cached a result, and
        // recurses until PHP's max_execution_time kills the request. hasResolvedGuards()
        // + hasUser() only peek at an already-resolved guard, never triggering a lookup
        // themselves — the same pattern Telescope's own core tagging uses internally.
        Telescope::tag(fn (IncomingEntry $entry) => Auth::hasResolvedGuards() && Auth::hasUser()
            ? ['User:'.Auth::id()]
            : []);
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Telescope's own defaults already hide password/password_confirmation
        // (see vendor/laravel/telescope/src/Telescope.php), but not current_password,
        // which UpdatePasswordRequest uses — without this it lands in plaintext in
        // database/telescope.sqlite on every failed (and, in local, every successful)
        // password-change request.
        Telescope::hideRequestParameters(['_token', 'current_password']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', User::isAdminGate());
    }

    /**
     * Configure the Telescope authorization services.
     *
     * The vendor default bypasses the gate entirely when app()->environment('local'),
     * which is also the environment real local dev machines run with — leaving
     * Telescope open to any visitor, authenticated or not. Always defer to the
     * viewTelescope gate instead, regardless of environment.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(fn ($request) => Gate::check('viewTelescope', [$request->user()]));
    }
}
