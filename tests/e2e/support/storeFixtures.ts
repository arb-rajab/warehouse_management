/// <reference types="node" />
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/** Seed only the dedicated browser-test database. */
export function seedStoreFixtures(php: string): void {
    execFileSync(
        'php',
        [
            '-r',
            String.raw`
                require 'vendor/autoload.php';
                $app = require 'bootstrap/app.php';
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                if (!app()->environment('testing') || !str_ends_with(config('database.connections.sqlite.database'), '/database/e2e.sqlite')) {
                    throw new RuntimeException('Store browser fixtures require their dedicated database');
                }
            ` + php,
        ],
        {
            cwd: fileURLToPath(new URL('../../../', import.meta.url)),
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: fileURLToPath(
                    new URL('../../../database/e2e.sqlite', import.meta.url),
                ),
                TELESCOPE_ENABLED: 'false',
                PULSE_ENABLED: 'false',
            },
        },
    );
}

/** Repeatable RTL setup in the same isolated database as the browser harness. */
export function ensureStoreRtlLanguage(): void {
    seedStoreFixtures(String.raw`
        if (!app()->environment('testing') || !str_ends_with(config('database.connections.sqlite.database'), '/database/e2e.sqlite')) {
            throw new RuntimeException('RTL fixtures require the dedicated browser database');
        }
        $language = App\Models\Language::query()->firstOrNew(['code' => 'ar']);
        $language->forceFill(['name' => 'Arabic', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1])->save();
    `);
}
