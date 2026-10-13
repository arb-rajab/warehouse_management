/// <reference types="node" />
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { php } from './editor';

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

/** Runs `$expression` against the named product and returns its JSON. */
export function storedProduct(name: string, expression: string): unknown {
    const file = join(
        tmpdir(),
        `store-product-${Date.now()}-${Math.random()}.json`,
    );
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::query()->where('name', ${php(name)})->sole();
        file_put_contents(${php(file)}, json_encode(${expression}));
    `);

    return JSON.parse(readFileSync(file, 'utf8'));
}
