<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class HealthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewHealth', User::isAdminGate());

        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            UsedDiskSpaceCheck::new(),
            ScheduleCheck::new(),
            QueueCheck::new(),
            BackupsCheck::new()
                ->onDisk('local')
                ->locatedAt(config('backup.backup.name'))
                ->youngestBackShouldHaveBeenMadeBefore(now()->subDay()),
            DebugModeCheck::new()->expectedToBe(false)->if($this->app->isProduction()),
            OptimizedAppCheck::new()->if($this->app->isProduction()),
        ]);
    }
}
