<?php

test('backup.notifications.mail.to defaults to an empty string, not a hardcoded example address, when BACKUP_NOTIFICATION_EMAIL is unset', function () {
    expect(getenv('BACKUP_NOTIFICATION_EMAIL'))->toBeFalse('this test only proves the default when BACKUP_NOTIFICATION_EMAIL is not set in the environment');

    expect(config('backup.notifications.mail.to'))->toBe('');
});

test('health.notifications.mail.to defaults to an empty string, not a hardcoded example address, when HEALTH_TO_ADDRESS is unset', function () {
    expect(getenv('HEALTH_TO_ADDRESS'))->toBeFalse('this test only proves the default when HEALTH_TO_ADDRESS is not set in the environment');

    expect(config('health.notifications.mail.to'))->toBe('');
});
