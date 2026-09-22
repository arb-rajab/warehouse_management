<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * There is no persistent queue:work daemon on every deployment target (the
 * Windows VPS has no Supervisor/systemd equivalent), so queue processing is
 * driven by the scheduler itself instead of a second always-on process.
 */
test('queue:work is scheduled every minute without overlapping', function () {
    $events = app(Schedule::class)->events();

    $queueWorkEvent = collect($events)->first(
        fn ($event) => str_contains($event->command, 'queue:work')
    );

    expect($queueWorkEvent)->not->toBeNull();
    expect($queueWorkEvent->command)->toContain('--stop-when-empty');
    expect($queueWorkEvent->command)->toContain('--max-time=55');
    expect($queueWorkEvent->expression)->toBe('* * * * *');
    expect($queueWorkEvent->withoutOverlapping)->toBeTrue();
});

/**
 * withoutOverlapping()'s cache mutex defaults to a 24-hour expiry, and
 * releaseOnTerminationSignals depends on pcntl, which doesn't exist on
 * Windows (this app's deployment target). An unreleased mutex from an
 * unexpected reboot/OOM would otherwise skip every run for a full day, so
 * every everyMinute()/hourly() entry must bound its mutex well below that.
 */
test('every frequently-run schedule entry bounds its withoutOverlapping mutex well below the 24-hour default', function () {
    $events = app(Schedule::class)->events();

    $frequentCommands = ['queue:work', 'health:check', 'health:schedule-check-heartbeat'];

    foreach ($frequentCommands as $needle) {
        $event = collect($events)->first(fn ($event) => str_contains($event->command, $needle));

        expect($event)->not->toBeNull("no scheduled event found for [{$needle}]");
        expect($event->withoutOverlapping)->toBeTrue("[{$needle}] must use withoutOverlapping()");
        expect($event->expiresAt)->toBeLessThanOrEqual(5, "[{$needle}]'s mutex expiry must be bounded, not the 1440-minute default");
    }
});

test('the daily backup and hourly sync commands bound their withoutOverlapping mutex to their own runtime, not the 24-hour default', function () {
    $events = app(Schedule::class)->events();

    $boundedCommands = [
        'backup:clean' => 60,
        'backup:run' => 120,
        'products:sync' => 30,
    ];

    foreach ($boundedCommands as $needle => $maxMinutes) {
        $event = collect($events)->first(fn ($event) => str_contains($event->command, $needle));

        expect($event)->not->toBeNull("no scheduled event found for [{$needle}]");
        expect($event->withoutOverlapping)->toBeTrue("[{$needle}] must use withoutOverlapping()");
        expect($event->expiresAt)->toBeLessThanOrEqual($maxMinutes, "[{$needle}]'s mutex expiry must be bounded, not the 1440-minute default");
    }
});

test('products:sync reports a scheduled run failure instead of failing silently', function () {
    $events = app(Schedule::class)->events();

    $event = collect($events)->first(fn ($event) => str_contains($event->command, 'products:sync'));

    expect($event)->not->toBeNull();

    $afterCallbacks = (new ReflectionProperty($event, 'afterCallbacks'))->getValue($event);

    expect($afterCallbacks)->not->toBeEmpty();
});
