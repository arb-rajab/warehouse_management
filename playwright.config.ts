import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const baseURL = 'http://127.0.0.1:8010';
const dbPath = fileURLToPath(new URL('./database/e2e.sqlite', import.meta.url));
/**
 * Telescope/Pulse/Health each live on their own sqlite connection (see
 * config/database.php) that defaults to a shared on-disk file
 * (database/telescope.sqlite etc.) used by normal local dev. `migrate:fresh`
 * only drops tables on the default connection, so without overriding these
 * too, its telescope/pulse/health migrations try to re-create tables that
 * already exist on that shared file and crash the webServer with "table
 * already exists" — this bit real CI the first time e2e ran there. Give
 * each its own dedicated e2e file instead (same reasoning as `dbPath`
 * above); `:memory:` (phpunit.xml's approach) doesn't work here because
 * `php artisan serve`'s built-in dev server spawns a fresh PHP process per
 * request, so an in-memory sqlite db would reset — and lose its migrated
 * tables — between the migrate step and the first request.
 */
const telescopeDbPath = fileURLToPath(
    new URL('./database/e2e-telescope.sqlite', import.meta.url),
);
const pulseDbPath = fileURLToPath(
    new URL('./database/e2e-pulse.sqlite', import.meta.url),
);
const healthDbPath = fileURLToPath(
    new URL('./database/e2e-health.sqlite', import.meta.url),
);

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command:
            'touch database/e2e-telescope.sqlite database/e2e-pulse.sqlite database/e2e-health.sqlite && php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8010',
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: dbPath,
            TELESCOPE_DB_DATABASE: telescopeDbPath,
            PULSE_DB_DATABASE: pulseDbPath,
            HEALTH_DB_DATABASE: healthDbPath,
            /**
             * POST /login carries the `honeypot` middleware, which rejects
             * any submission faster than config('honeypot.amount_of_seconds')
             * (1s) after the form rendered — real spam protection, not
             * something any e2e spec tests. Pest's login tests never trip
             * this (they post directly, skipping the rendered form's timing
             * field entirely), but Playwright's loginAsAdmin() fills and
             * submits far faster than a human, so nearly every e2e spec
             * failed at login until this was disabled — no e2e spec exists
             * to actually verify anti-spam behavior, so there's nothing lost
             * by turning it off here, same reasoning as disabling
             * Telescope/Pulse for Pest in phpunit.xml.
             */
            HONEYPOT_ENABLED: 'false',
            SESSION_DRIVER: 'cookie',
            CACHE_STORE: 'array',
            QUEUE_CONNECTION: 'sync',
        },
    },
});
