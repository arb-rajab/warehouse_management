<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
    HealthServiceProvider::class,
    // Merged from the legacy store (see
    // .ai/rules/legacy-albaraka-holland.md section 2): AuthServiceProvider's
    // Gate::before() Super Admin bypass and EventServiceProvider's listener
    // are additive. RouteServiceProvider mounts the store's own routes under
    // /temp — see that class's docblock. BroadcastServiceProvider is left
    // unregistered, matching the legacy app's own commented-out provider
    // entry (config/app.php's 'providers' array had it disabled).
    AuthServiceProvider::class,
    EventServiceProvider::class,
    RouteServiceProvider::class,
];
