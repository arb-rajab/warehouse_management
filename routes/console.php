<?php

use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Schedule::command('telescope:prune --hours='.config('telescope.prune_hours'))->daily();

Schedule::command('backup:clean')->daily()->at('01:00')->withoutOverlapping();
Schedule::command('backup:run --only-db')->daily()->at('01:30')->withoutOverlapping();

Schedule::command('health:check')->everyMinute();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();
Schedule::command('health:queue-check-heartbeat')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]])->daily();
