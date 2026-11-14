import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { e2ePorts } from './tests/e2e/support/ports.mjs';

// Ports come from E2E_PORT/E2E_BACKEND_PORT (default 8010/8011), so runner
// instances sharing one machine each use their own pair (see ports.mjs).
const { baseURL, publicPort, backendPort } = e2ePorts();
const usesHTTPS = baseURL.startsWith('https://');
// Plain commands only: Playwright runs this through cmd.exe on Windows. The
// database setup is a Node script for the same reason.
const serverCommand = usesHTTPS
    ? 'node tests/e2e/support/serve-https.mjs'
    : `php artisan serve --no-reload --host=127.0.0.1 --port=${publicPort}`;
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
        ignoreHTTPSErrors: baseURL.startsWith('https://'),
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
            ? {
                  executablePath:
                      process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH,
              }
            : {},
        // No `retries` is configured, so 'on-first-retry' never actually
        // captured anything -- there's no first retry to trigger on.
        // 'retain-on-failure' captures a trace/screenshot/video for any
        // failed test on its only attempt, which is what CI needs to
        // diagnose a failure without reproducing it locally.
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        // prepare-e2e.mjs resets only dedicated e2e files (including ancillary
        // connections that migrate:fresh does not drop), migrates and seeds.
        // --no-reload keeps these DB overrides in the PHP server child; serve
        // otherwise discards them when .env exists.
        command: `node tests/e2e/support/prepare-e2e.mjs && ${serverCommand}`,
        url: baseURL,
        ignoreHTTPSErrors: baseURL.startsWith('https://'),
        // Never reuse: a server already answering on this URL belongs to
        // another run (possibly another runner instance), with its own
        // database. Playwright then fails with "already used" instead.
        reuseExistingServer: false,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 5_000 },
        timeout: 120_000,
        env: {
            APP_ENV: 'testing',
            APP_URL: baseURL,
            E2E_BACKEND_PORT: String(backendPort),
            CSP_REPORT_ONLY: 'false',
            TRUSTED_PROXIES: usesHTTPS
                ? '127.0.0.1'
                : (process.env.TRUSTED_PROXIES ?? ''),
            TELESCOPE_ENABLED: 'false',
            PULSE_ENABLED: 'false',
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
            // The array driver keeps every message in memory, so no spec can
            // deliver real mail. The username only satisfies the newsletter's
            // "SMTP configured" guard; nothing connects with it.
            MAIL_MAILER: 'array',
            MAIL_USERNAME: 'e2e-array-mailer',
        },
    },
});
