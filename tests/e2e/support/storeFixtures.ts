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
