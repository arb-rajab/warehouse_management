<?php

test('every sqlite connection uses WAL journal mode with a busy timeout', function () {
    foreach (['sqlite', 'telescope', 'pulse', 'health'] as $connection) {
        expect(config("database.connections.{$connection}.journal_mode"))->toBe('wal')
            ->and(config("database.connections.{$connection}.synchronous"))->toBe('normal')
            ->and(config("database.connections.{$connection}.busy_timeout"))->toBe(5000);
    }
});
