<?php

use App\Providers\AppServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
    HealthServiceProvider::class,
];
