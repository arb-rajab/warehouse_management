---
paths:
  - 'config/backup.php,config/database.php'
  - config/database.php
---

# Config

## spatie/laravel-backup's sqlite dump needs the sqlite3 CLI, not just the PHP extension
Scheduled backups run `backup:run --only-db` (see routes/console.php), which for a sqlite connection shells out to the `sqlite3` command-line binary (spatie/db-dumper), separate from PHP's pdo_sqlite/sqlite3 extensions. If a machine doesn't have it on PATH, set SQLITE3_DUMP_BINARY_PATH in its .env (read by the 'dump.dump_binary_path' key on the 'sqlite' connection in config/database.php) to the directory containing sqlite3.exe/sqlite3. Production on MySQL/Postgres needs mysqldump/pg_dump instead, which is a separate concern.

## All sqlite connections run WAL + busy_timeout — required even when the app DB is MySQL/Postgres
The 'sqlite', 'telescope', 'pulse', and 'health' connections all set journal_mode=wal, synchronous=normal, busy_timeout=5000. Telescope/Pulse/Health are hardcoded to sqlite drivers regardless of the app's default DB_CONNECTION, so this matters in every environment, including production where the main DB is MySQL/Postgres. Without WAL, a scheduled write (health:check, telescope:prune, a queued job) holds an exclusive lock on the whole file and blocks concurrent readers/writers on the same connection — this caused real request hangs/PHP max_execution_time fatals when Telescope+Pulse were added, made worse on a Windows dev box by antivirus re-scanning the frequently-rewritten sqlite files. SQLite silently no-ops these pragmas for the ':memory:' databases phpunit.xml uses, so tests are unaffected. Covered by tests/Feature/DatabaseConnectionsTest.php.
