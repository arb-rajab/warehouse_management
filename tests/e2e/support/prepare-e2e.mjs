import { execFileSync } from 'node:child_process';
import { closeSync, openSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// The Playwright web server's setup, before it starts serve-https.mjs (or
// `php artisan serve`). Node rather than a shell chain: Playwright runs its
// webServer command through cmd.exe on Windows, where `rm`, `touch` and
// single-quoted arguments do not exist. No shell is involved here, so the
// seeder's backslashes are passed as they are.
//
// Reset only dedicated e2e files, including the ancillary connections that
// migrate:fresh does not drop (see playwright.config.ts). Fresh checkouts also
// need the public disk link for uploaded images.
const root = new URL('../../../', import.meta.url);
const databases = [
    'database/e2e.sqlite',
    'database/e2e-telescope.sqlite',
    'database/e2e-pulse.sqlite',
    'database/e2e-health.sqlite',
].map((file) => fileURLToPath(new URL(file, root)));

for (const file of databases) {
    rmSync(file, { force: true });
}

for (const file of databases) {
    closeSync(openSync(file, 'w'));
}

const commands = [
    ['php', ['artisan', 'storage:link', '--no-interaction']],
    ['php', ['artisan', 'migrate:fresh', '--seed', '--force']],
    [
        'php',
        [
            'artisan',
            'db:seed',
            '--class=Tests\\Fixtures\\StoreCampaignBrowserSeeder',
            '--force',
        ],
    ],
    ['php', ['tests/e2e/support/seed-store-orders.php']],
];

for (const [command, args] of commands) {
    execFileSync(command, args, { cwd: fileURLToPath(root), stdio: 'inherit' });
}
