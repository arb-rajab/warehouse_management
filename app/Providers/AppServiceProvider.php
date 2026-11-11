<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\ApiResourceResponse;
use App\Support\MergeRepeatedResponseAttributes;
use App\Support\StoreMailSettings;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    private const API_DOCUMENTATION_TAGS = [
        'warehouse.auth' => 'Warehouse · Authentication',
        'warehouse.inventory' => 'Warehouse · Inventory',
        'warehouse.pallets' => 'Warehouse · Pallets',
        'warehouse.audit' => 'Warehouse · Audit logs',
        'store.auth' => 'Store · Authentication',
        'store.catalogue' => 'Store · Catalogue',
        'store.orders' => 'Store · Orders',
        'store.customers' => 'Store · Customers',
        'store.sellers' => 'Store · Sellers',
        'store.delivery' => 'Store · Delivery & Trips',
        'store.warehouse' => 'Store · Warehouse integration',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One GD-backed Intervention Image manager for ImageOptimizer and
        // ThumbnailService. Both options keep the v2 behaviour: ImageOptimizer
        // applies the EXIF orientation itself (it reads the tag without
        // ext-exif), and a GIF decodes to its first frame only.
        $this->app->singleton(ImageManager::class, fn (): ImageManager => new ImageManager(
            new GdDriver,
            autoOrientation: false,
            decodeAnimation: false,
        ));

        // One per process: it remembers the `.env` mail values it overrides.
        $this->app->singleton(StoreMailSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApiDocumentation();
        $this->applyStoreMailSettings();

        // Merged from the legacy store's AppServiceProvider (see
        // .ai/rules/legacy-albaraka-holland.md section 2). Its migrations
        // create indexed varchar columns without an explicit length, which
        // exceeds MySQL's index-key-length limit under utf8mb4 unless capped
        // here. Paginator::useBootstrap() only changes the default Blade
        // pagination view (`$items->links()`); this app's own paginated
        // responses go through paginated() as JSON and are unaffected.
        Schema::defaultStringLength(191);
        Paginator::useBootstrap();

        Gate::define('viewPulse', User::isAdminGate());

        // spatie/laravel-backup silently skips encryption when no password is
        // set. A production backup holds the whole shared store database, so
        // refuse to write one unencrypted.
        Event::listen(function (CommandStarting $event): void {
            if ($event->command === 'backup:run' && app()->isProduction() && blank(config('backup.backup.password'))) {
                throw new RuntimeException('BACKUP_ARCHIVE_PASSWORD must be set to run backups in production.');
            }
        });

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

        // The admin panel's own password change: it asks for the current
        // password, so without a limit a stolen session could guess it. Keyed on
        // the signed-in user, falling back to the IP.
        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(5)->by('password-change|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // The legacy store's login, and its emailed and texted codes (password
        // reset, verification): 6 digits is 900,000 guesses, and none of these
        // routes had any limit. Per account and IP, per account across IPs
        // (the one that stops a spread-out attack), and per IP across accounts
        // (the one that stops one address trying many). A request that names
        // no account, such as the token-authenticated `confirm_code`, falls
        // back to its user, then to the IP limit alone: sharing one empty key
        // would let anonymous callers lock each other out. A value that is not
        // text (an array, which the endpoint's own rules refuse) names no
        // account; cast to a string it was a 500 before the endpoint ran.
        RateLimiter::for('store-auth', function (Request $request): array {
            $account = collect([$request->input('email'), $request->input('email_or_phone'), $request->input('phone')])
                ->first(fn (mixed $value): bool => is_scalar($value) && (bool) $value);
            $account = Str::lower(trim((string) $account));
            $account = $account !== '' ? $account : ($request->user('store_api') ? 'user:'.$request->user('store_api')->getKey() : '');

            $limits = [Limit::perMinute(30)->by('store-auth-ip|'.$request->ip())];

            if ($account !== '') {
                $limits[] = Limit::perMinute(5)->by('store-auth|'.$account.'|'.$request->ip());
                $limits[] = Limit::perMinutes(15, 10)->by('store-auth|'.$account);
            }

            return $limits;
        });

        // The store's sign-up: the sole job of the `rate.limit` middleware this
        // replaces, which counted attempts with a get-then-increment on the
        // cache (two simultaneous requests both read the same count) and
        // was keyed on IP and path with a hand-rolled 429 body. Same budget, 5
        // sign-ups per IP per 15 minutes, on the framework's atomic limiter.
        RateLimiter::for('store-signup', fn (Request $request): Limit => Limit::perMinutes(15, 5)->by('store-signup|'.$request->ip()));

        // `user('sanctum')`, never a bare `user()`: with no guard argument the
        // resolver uses auth.defaults.guard (`web`, session driver), and the api
        // middleware group has no StartSession, so that guard resolves null on
        // every API request and the key always fell back to the IP. A warehouse
        // whose tablets NAT out through one address then shared a single 60/min
        // bucket, while an individual abusive token was never throttled at all.
        // Naming the guard reads the bearer token directly and does not require
        // `auth:sanctum` (which runs after this middleware) to have resolved yet.
        // The legacy store's tokens resolve on `store_api`, not `sanctum`, and
        // both user tables' ids overlap, so the key carries the model class.
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user('sanctum') ?? $request->user('store_api');

            return Limit::perMinute(60)->by($user ? $user::class.':'.$user->getKey() : $request->ip());
        });

        // The warehouse client is one server calling in with a static token, so the
        // bucket is per IP and sized for a sync that pages through every list.
        RateLimiter::for('wms', fn (Request $request): Limit => Limit::perMinute(120)->by('wms|'.$request->ip()));
    }

    /**
     * The SMTP settings saved in the store admin win over MAIL_* (StoreMailSettings,
     * .ai/rules/admin-env-overrides.md). Applied on web requests and before each
     * queued job, so a running worker picks up a save; never for other console
     * commands, since `config:cache` and `optimize` boot this provider and would
     * write the decrypted SMTP password into bootstrap/cache/config.php.
     */
    private function applyStoreMailSettings(): void
    {
        if (! $this->app->runningInConsole()) {
            $this->app->make(StoreMailSettings::class)->apply();
        }

        Event::listen(JobProcessing::class, function (): void {
            $this->app->make(StoreMailSettings::class)->apply();
        });
    }

    private function configureApiDocumentation(): void
    {
        Scramble::registerExtension(ApiResourceResponse::class);

        Scramble::configure()
            ->routes(fn (Route $route): bool => str_starts_with($route->uri(), 'api/') && ! $route->isFallback)
            // Appended after Scramble's ResponseExtension, which keeps only the last #[Response] per status.
            ->withOperationTransformers(MergeRepeatedResponseAttributes::class)
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme('privateToken', SecurityScheme::apiKey('header', 'private-token'));
                $openApi->tags = array_map(fn (string $name): Tag => new Tag($name), array_values(self::API_DOCUMENTATION_TAGS));
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $uri = $routeInfo->route->uri();
                $controller = class_basename($routeInfo->route->getControllerClass() ?? '');
                $warehouse = str_starts_with($uri, 'api/') && ! str_starts_with($uri, 'api/v1/store/');
                $tag = match (true) {
                    $warehouse => match ($controller) {
                        'AuthController' => 'warehouse.auth',
                        'PalletController' => 'warehouse.pallets',
                        'CellStatusLogController', 'CellVerificationReportController', 'CellVerificationRoundController' => 'warehouse.audit',
                        default => 'warehouse.inventory',
                    },
                    str_starts_with($uri, 'api/v1/store/warehouse/') => 'store.warehouse',
                    str_starts_with($uri, 'api/v1/store/seller/') => 'store.sellers',
                    str_starts_with($uri, 'api/v1/store/trips/'),
                    str_starts_with($uri, 'api/v1/store/driver/'),
                    str_starts_with($uri, 'api/v1/store/delivery-boy/'),
                    str_starts_with($uri, 'api/v1/store/get-user-coordinates/') => 'store.delivery',
                    str_starts_with($uri, 'api/v1/store/auth/'), $controller === 'UserController' => 'store.auth',
                    in_array($controller, [
                        'CartController', 'CheckoutController', 'OrderController', 'PurchaseHistoryController',
                        'DigitalProductController', 'ShippingController', 'CarrierController', 'PaymentController',
                        'PaymentTypesController', 'OfflinePaymentController', 'WalletController', 'ClubpointController',
                        'RefundRequestController', 'BkashController', 'IyzicoController', 'NagadController', 'SslCommerzController',
                    ], true) => 'store.orders',
                    in_array($controller, [
                        'AddressController', 'CustomerController', 'CustomerPackageController', 'ProfileController',
                        'RepresentativePaymentController', 'WishlistController', 'ChatController', 'FileController', 'AizUploadController',
                    ], true) => 'store.customers',
                    default => 'store.catalogue',
                };
                $operation->setTags([self::API_DOCUMENTATION_TAGS[$tag]]);
                $operation->setAttribute('groupWeight', $warehouse ? 0 : 1);

                if (in_array('wms.auth', $routeInfo->route->gatherMiddleware(), true)) {
                    $operation->security = [new SecurityRequirement(['privateToken' => []])];
                }
            });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        JsonResource::withoutWrapping();

        Date::use(CarbonImmutable::class);

        // Moves Sanctum off the default `personal_access_tokens` table,
        // which the store app owns in the shared production database.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Turns three conventions this codebase already follows by hand into
        // enforced ones: no lazy loading (the N+1 guard), no reading an
        // attribute a narrow select() left out, and no silently dropping a
        // non-fillable attribute on fill/create.
        //
        // Named environments rather than ! isProduction(): staging runs
        // APP_ENV=staging, so that test would leave the guards on in front of
        // real users, where a missed eager load should degrade to a slow page
        // rather than a 500. Listing the environments a developer is watching
        // also means any environment added later is off by default, which is
        // the safe direction to be wrong in. EloquentStrictModeTest asserts
        // each of the three individually, so a change to what this bundles
        // cannot quietly drop one.
        Model::shouldBeStrict(app()->environment('local', 'testing'));

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
