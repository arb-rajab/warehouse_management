import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const baseURL = 'http://127.0.0.1:8010';
const dbPath = fileURLToPath(new URL('./database/e2e.sqlite', import.meta.url));

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
            'php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8010',
        url: baseURL,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            APP_ENV: 'testing',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: dbPath,
            SESSION_DRIVER: 'cookie',
            CACHE_STORE: 'array',
            QUEUE_CONNECTION: 'sync',
        },
    },
});
