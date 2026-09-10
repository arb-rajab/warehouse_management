<?php

use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Schedule::command('telescope:prune --hours='.config('telescope.prune_hours'))->daily();

// No persistent queue:work daemon runs on every deployment target (e.g. the
// Windows VPS has no Supervisor/systemd equivalent), so the scheduler itself
// drives queue processing: schedule:run is the one OS-level trigger every
// environment already needs, and this piggybacks on it instead of requiring
// a second always-on worker process.
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();

Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping();
Schedule::command('backup:run --only-db')->daily()->at('01:30')->withoutOverlapping();

Schedule::command('health:check')->everyMinute();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
Schedule::command('health:queue-check-heartbeat')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]])->daily();
