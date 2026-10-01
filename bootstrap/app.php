<?php

use App\Http\Middleware\AppLanguage;
use App\Http\Middleware\BlockMaliciousRequests;
use App\Http\Middleware\CheckoutMiddleware;
use App\Http\Middleware\EnsureMinimumAppVersion;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Http\Middleware\EnsurePaymentGatewayEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsAppUserUnbanned;
use App\Http\Middleware\IsCustomer;
use App\Http\Middleware\IsDeliveryBoy;
use App\Http\Middleware\IsDriver;
use App\Http\Middleware\IsSeller;
use App\Http\Middleware\IsUnbanned;
use App\Http\Middleware\IsUser;
use App\Http\Middleware\LogRequestTime;
use App\Http\Middleware\RaizerSuperAdmin;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetLocaleFromHeader;
use App\Http\Middleware\StoreInertiaPreviousUrl;
use App\Http\Middleware\UseStoreAuthGuard;
use App\Http\Middleware\WmsAuthMiddleware;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Spatie\Honeypot\ProtectAgainstSpam;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SecureHeaders wraps the WAF, not the other way around: BlockMaliciousRequests
        // aborts with a 403 from inside the pipeline, so anything appended after it
        // never runs on a blocked request and those responses would ship bare.
        $middleware->append(SecureHeadersMiddleware::class);
        $middleware->append(BlockMaliciousRequests::class);

        $trustedProxies = array_filter(explode(',', (string) Env::get('TRUSTED_PROXIES', '')));

        if ($trustedProxies !== []) {
            // Omitting `headers:` keeps the framework default, which includes
            // HEADER_X_FORWARDED_HOST — a trusted proxy could then have its
            // X-Forwarded-Host header used verbatim for url()/route() generation,
            // e.g. redirect()->route('login') issuing a Location on an attacker-
            // controlled host. FOR|PORT|PROTO is everything this app actually
            // needs from a real reverse proxy/LB.
            $middleware->trustProxies(
                at: array_values($trustedProxies),
                headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            StoreInertiaPreviousUrl::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePasswordHasBeenChanged::class,
        ]);

        $middleware->api(append: [SetLocaleFromHeader::class, EnsureMinimumAppVersion::class]);
        $middleware->throttleApi();

        // `auth` (Authenticate) is in the framework's middleware priority list,
        // which lets the router run it ahead of any route middleware that is
        // not, UseStoreAuthGuard included. It then checked this app's own
        // 'web' guard on every /temp route, and no store user could get past
        // it. Listing UseStoreAuthGuard just before it keeps the order.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, UseStoreAuthGuard::class);

        // The legacy store under /temp has its own login page and home page:
        // a guest there, or a signed-in store user on a guest-only page,
        // belongs on those rather than on this app's admin login/dashboard.
        $middleware->redirectTo(
            guests: fn (Request $request): string => $request->is('temp', 'temp/*') ? route('shop.login') : route('login'),
            users: fn (Request $request): string => $request->is('temp', 'temp/*') ? route('home') : route('admin.dashboard'),
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'honeypot' => ProtectAgainstSpam::class,

            // Merged from the legacy store's app/Http/Kernel.php $routeMiddleware
            // (see .ai/rules/legacy-albaraka-holland.md section 2) — every name its
            // moved routes/shop-*.php files reference that isn't already one of
            // Laravel's own default aliases (auth, guest, verified, can, etc., which
            // this app's own routes already rely on and must keep resolving to the
            // framework's own classes, not a legacy override).
            'app_language' => AppLanguage::class,
            'app_user_unbanned' => IsAppUserUnbanned::class,
            'admin' => IsAdmin::class,
            'seller' => IsSeller::class,
            'customer' => IsCustomer::class,
            'user' => IsUser::class,
            'unbanned' => IsUnbanned::class,
            'checkout' => CheckoutMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'RaizerSuperAdmin' => RaizerSuperAdmin::class,
            'wms.auth' => WmsAuthMiddleware::class,
            'log.request.time' => LogRequestTime::class,
            'rate.limit' => RateLimitMiddleware::class,
            'driver' => IsDriver::class,
            'delivery_boy' => IsDeliveryBoy::class,
            'payment.gateway' => EnsurePaymentGatewayEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
