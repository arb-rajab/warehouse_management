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

// Deliberately NOT scheduled: every command below changes data, and none may be
// put on the scheduler without sign-off from the business owner. Run them by
// hand (php artisan <name>) until that referral exists.
//
//   carts:purge-old                 permanently deletes carts soft-deleted over 30 days ago
//   check:unordered-carts           notifies admins and flags carts untouched for 5 days
//   check:customers                 unverifies customers with no order for 30 days (hides prices)
//   import:members, import:agents   create and update store users from the otajer ERP
//   import:brands, import:categories, import:collections,
//   import:offer-new-products, import:all-products
//                                   overwrite catalogue data from the otajer ERP
//   sync:users                      creates accounts in the otajer ERP for store users
//   fix:members                     bans duplicate members (preview with --dry-run)
//   check:commands                  runs the product import once an admin queues it
