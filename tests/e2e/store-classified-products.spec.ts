import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

const fixtureName = 'Browser classified candidate';

/** Fixtures go only into Playwright's dedicated database, never the dev DB. */
function classifiedFixtures(create: boolean): void {
    execFileSync(
        'php',
        [
            '-r',
            `
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
            App\\Models\\CustomerProduct::where('name', '${fixtureName}')->delete();
            if (${create ? 'true' : 'false'}) {
                Illuminate\\Support\\Facades\\DB::table('languages')->updateOrInsert(
                    ['code' => 'ar'], ['name' => 'Arabic', 'rtl' => 1]
                );
                for ($i = 0; $i < 21; $i++) {
                    App\\Models\\CustomerProduct::forceCreate([
                        'name' => '${fixtureName}', 'slug' => 'browser-classified-'.$i,
                        'added_by' => 'customer', 'status' => 1, 'published' => 0,
                    ]);
                }
            }
        `,
        ],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: fileURLToPath(
                    new URL('../../database/e2e.sqlite', import.meta.url),
                ),
                PAO_DISABLE: '1',
            },
        },
    );
}

test('classified writes use native confirmation and retain RTL pagination and dark contrast', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    classifiedFixtures(true);

    try {
        await page.goto('/temp/admin/classified_products');
        await expect(
            page.getByRole('heading', { name: 'Classified products' }),
        ).toBeVisible();
        const row = page
            .getByRole('row', { name: new RegExp(fixtureName) })
            .first();
        await row.getByRole('button', { name: 'Publish', exact: true }).click();
        await expect(
            row.getByRole('button', { name: 'Unpublish', exact: true }),
        ).toBeVisible();
        page.once('dialog', (dialog) => dialog.dismiss());
        await row.getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(
            page.getByRole('row', { name: new RegExp(fixtureName) }),
        ).toHaveCount(20);
        page.once('dialog', (dialog) => dialog.accept());
        await row.getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(
            page.getByRole('button', { name: 'Unpublish', exact: true }),
        ).toHaveCount(0);
        await page.getByRole('combobox').selectOption('10');
        await expect(page).toHaveURL(/per_page=10/);
        await page.getByRole('button', { name: 'Arabic', exact: true }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        const nextIcon = page.locator('main nav a').last().locator('svg');
        await expect(nextIcon).toHaveCSS('rotate', '180deg');
        await page.evaluate(() =>
            document.documentElement.classList.add('dark'),
        );
        const colors = await page.locator('main h1').evaluate((heading) => {
            const toRgb = (color: string): number[] => {
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 1;
                const context = canvas.getContext('2d')!;
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);

                return Array.from(context.getImageData(0, 0, 1, 1).data).slice(
                    0,
                    3,
                );
            };

            return [
                toRgb(getComputedStyle(heading).color),
                toRgb(
                    getComputedStyle(document.querySelector('.min-h-screen')!)
                        .backgroundColor,
                ),
            ];
        });
        expect(contrastRatio(colors[0], colors[1])).toBeGreaterThanOrEqual(4.5);
    } finally {
        classifiedFixtures(false);
    }
});
