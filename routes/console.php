<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Schedule::command('telescope:prune --hours='.config('telescope.prune_hours'))->daily();

// No persistent queue:work daemon runs on every deployment target (e.g. the
// Windows VPS has no Supervisor/systemd equivalent), so the scheduler itself
// drives queue processing: schedule:run is the one OS-level trigger every
// environment already needs, and this piggybacks on it instead of requiring
// a second always-on worker process.
//
// withoutOverlapping()'s cache mutex defaults to a 24-hour expiry, and its
// releaseOnTerminationSignals option depends on pcntl, which doesn't exist on
// Windows. An unexpected reboot or PHP OOM mid-run would otherwise leave the
// mutex held for a full day, silently skipping every run in between — bound
// each mutex below to roughly the entry's own worst-case runtime instead.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping(2);

Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping(10);
Schedule::command('backup:run --only-db')->daily()->at('01:30')->withoutOverlapping(60);

Schedule::command('products:sync')->hourly()->withoutOverlapping(15)->onFailure(function (): void {
    Log::error('Scheduled products:sync run failed.');
});

Schedule::command('health:check')->everyMinute()->withoutOverlapping(2);
Schedule::command('health:schedule-check-heartbeat')->everyMinute()->withoutOverlapping(2);
Schedule::command('health:queue-check-heartbeat')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]])->daily();
