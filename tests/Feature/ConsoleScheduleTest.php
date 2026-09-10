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
