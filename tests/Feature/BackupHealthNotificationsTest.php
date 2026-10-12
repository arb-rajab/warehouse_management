<?php

test('backup.notifications.mail.to defaults to an .invalid-TLD placeholder, not the hardcoded your@example.com, when BACKUP_NOTIFICATION_EMAIL is unset', function () {
    expect(getenv('BACKUP_NOTIFICATION_EMAIL'))->toBeFalse('this test only proves the default when BACKUP_NOTIFICATION_EMAIL is not set in the environment');

    // spatie/laravel-backup eagerly validates this as a real email address
    // during package discovery, so the fallback can't be blank — it must
    // still pass filter_var(..., FILTER_VALIDATE_EMAIL) while guaranteeing
    // delivery fails (the .invalid TLD is reserved by RFC 2606 to never
    // resolve) instead of silently reaching a real third-party inbox.
    expect(filter_var(config('backup.notifications.mail.to'), FILTER_VALIDATE_EMAIL))->not->toBeFalse();
    expect(config('backup.notifications.mail.to'))->toEndWith('@example.invalid');
});

test('health.notifications.mail.to defaults to an .invalid-TLD placeholder, not the hardcoded your@example.com, when HEALTH_TO_ADDRESS is unset', function () {
    expect(getenv('HEALTH_TO_ADDRESS'))->toBeFalse('this test only proves the default when HEALTH_TO_ADDRESS is not set in the environment');

    expect(filter_var(config('health.notifications.mail.to'), FILTER_VALIDATE_EMAIL))->not->toBeFalse();
    expect(config('health.notifications.mail.to'))->toEndWith('@example.invalid');
});
