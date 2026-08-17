---
paths:
  - 'config/telescope.php,database/migrations/*telescope*'
  - 'config/pulse.php,database/migrations/*pulse*'
---

# Config Migrations

## Telescope uses its own 'telescope' DB connection — keep TELESCOPE_DB_DATABASE=:memory: in phpunit.xml
Telescope entries are stored in a separate sqlite connection (`config/database.php` 'telescope' connection, file database/telescope.sqlite), configured via `config/telescope.php` storage.database.connection = env('TELESCOPE_DB_CONNECTION', 'telescope'). The published migration overrides its own connection via getConnection(), so it runs against this connection regardless of the app's default DB_CONNECTION. phpunit.xml sets TELESCOPE_DB_DATABASE=:memory: for the same reason DB_DATABASE is :memory: — without it, RefreshDatabase tries to re-create telescope tables in the real on-disk file every test run and fails with "table already exists".

## Pulse uses its own 'pulse' DB connection — keep PULSE_DB_DATABASE=:memory: in phpunit.xml
Like Telescope, Pulse entries are stored in a separate sqlite connection (config/database.php 'pulse' connection, file database/pulse.sqlite), set via config/pulse.php storage.database.connection = env('PULSE_DB_CONNECTION', 'pulse'). phpunit.xml sets PULSE_DB_DATABASE=:memory: for the same reason as TELESCOPE_DB_DATABASE — without it, RefreshDatabase tries to recreate pulse tables in the real on-disk file every test run and fails with "table already exists".

pulse.allowed_ips (config/pulse.php) defaults to env('PULSE_ALLOWED_IPS', env('TELESCOPE_ALLOWED_IPS', '')) — Pulse shares Telescope's IP allowlist by default; set PULSE_ALLOWED_IPS explicitly to diverge from it.
