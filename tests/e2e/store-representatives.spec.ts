/// <reference types="node" />
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

function representativeFixtures(create: boolean): void {
    execFileSync(
        'php',
        [
            '-r',
            `
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        Illuminate\\Support\\Facades\\DB::table('representative_payments')->where('id', 'browser-receipt')->delete();
        App\\Models\\Visit::where('note', 'Browser visit fixture')->delete();
        if (${create ? 'true' : 'false'}) {
            $rep = App\\Models\\StoreUser::where('email', 'store-admin@example.com')->firstOrFail();
            Illuminate\\Support\\Facades\\DB::table('representative_payments')->insert(['id' => 'browser-receipt', 'rep_id' => $rep->id, 'receipt_code' => 'Browser receipt', 'amount' => 125.50, 'status' => 'pending', 'date' => now(), 'created_at' => now(), 'updated_at' => now()]);
            App\\Models\\Visit::create(['rep_id' => $rep->id, 'customer_id' => $rep->id, 'note' => 'Browser visit fixture', 'visit_date' => '2026-10-08 12:00:00']);
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

test('representative package deletion uses a native cancellable confirmation', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/representatives/packages/create');
    await page
        .getByRole('textbox', { name: 'Name required', exact: true })
        .fill('Browser package');
    await page.getByLabel('Discount percentage').fill('12.5');
    await page
        .getByRole('button', { name: 'Save package', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Representative packages' }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: /Browser package/ });
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
});

test('payment decisions and selected visit deletion have native confirmations and date pickers support dark mode', async ({
    page,
}) => {
    representativeFixtures(true);

    try {
        await loginAsStoreAdmin(page);
        await page.goto('/temp/admin/admin/representatives/payments/index');
        const payment = page.getByRole('row', { name: /Browser receipt/ });
        page.once('dialog', (dialog) => dialog.dismiss());
        await payment
            .getByRole('button', { name: 'Accept', exact: true })
            .click();
        await expect(
            payment.getByRole('button', { name: 'Accept', exact: true }),
        ).toBeVisible();
        page.once('dialog', (dialog) => dialog.accept());
        await payment
            .getByRole('button', { name: 'Accept', exact: true })
            .click();
        await expect(
            payment.getByRole('button', { name: 'Accept', exact: true }),
        ).toHaveCount(0);
        await expect(payment).toContainText('Accepted');
        await page.goto('/temp/admin/representatives/visits/index');
        await page.emulateMedia({ colorScheme: 'dark' });
        await expect(page.getByLabel('From date')).toHaveCSS(
            'color-scheme',
            'dark',
        );
        await page.getByLabel('From date').fill('2026-10-01');
        await expect(page.getByLabel('To date')).toHaveAttribute(
            'min',
            '2026-10-01',
        );
        const visit = page.getByRole('row', { name: /Browser visit fixture/ });
        await visit.getByRole('checkbox').check();
        page.once('dialog', (dialog) => dialog.dismiss());
        await page
            .getByRole('button', { name: 'Delete selection', exact: true })
            .click();
        await expect(visit).toBeVisible();
        page.once('dialog', (dialog) => dialog.accept());
        await page
            .getByRole('button', { name: 'Delete selection', exact: true })
            .click();
        await expect(visit).toHaveCount(0);
    } finally {
        representativeFixtures(false);
    }
});
