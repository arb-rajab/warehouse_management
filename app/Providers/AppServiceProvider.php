<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('viewPulse', User::isAdminGate());

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));

            return [
                // Per (email, IP): the primary guard, keeps one attacker on
                // one IP from locking out a legitimate user sharing that email.
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                // Per email only: caps a distributed attack that spreads
                // guesses across many IPs to dodge the limit above.
                Limit::perMinute(20)->by($email),
            ];
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        JsonResource::withoutWrapping();

        RedirectIfAuthenticated::redirectUsing(fn () => route('admin.dashboard'));

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Turns three conventions this codebase already follows by hand into
        // enforced ones: no lazy loading (the N+1 guard), no reading an
        // attribute a narrow select() left out, and no silently dropping a
        // non-fillable attribute on fill/create. Off in production so a missed
        // eager load degrades to a slow page rather than a 500; on everywhere
        // else, including CI, so violations surface as failing tests.
        Model::shouldBeStrict(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
