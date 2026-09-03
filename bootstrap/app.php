<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Http\Middleware\EnsureMinimumAppVersion;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetLocaleFromHeader;
use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Spatie\Honeypot\ProtectAgainstSpam;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(BlockMaliciousRequests::class);
        $middleware->append(SecureHeadersMiddleware::class);

        $trustedProxies = array_filter(explode(',', (string) Env::get('TRUSTED_PROXIES', '')));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: array_values($trustedProxies));
        }

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [SetLocaleFromHeader::class, EnsureMinimumAppVersion::class]);
        $middleware->throttleApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'honeypot' => ProtectAgainstSpam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
