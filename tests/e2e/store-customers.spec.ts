import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

/** Only the dedicated browser-test database receives fixtures. */
function customerFixtures(): void {
    execFileSync(
        'php',
        [
            '-r',
            `
        require 'vendor/autoload.php';
        $app=require 'bootstrap/app.php';
        $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        Illuminate\\Support\\Facades\\DB::table('countries')->updateOrInsert(['name'=>'Netherlands'],['code'=>'NL','status'=>1]);
        $existing=App\\Models\\StoreUser::where('email','customer-migration-browser@example.com')->first();
        if($existing!==null){$existing->addresses()->delete();$existing->delete();}
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

test('customer create edit address and archive preserve Inertia workflows and native confirmation', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    customerFixtures();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/temp/admin/customers/create');
    await page.locator('#name').fill('Customer Migration Browser');
    await page.locator('#email').fill('customer-migration-browser@example.com');
    await page.locator('#phone').fill('31699990001');

    for (const field of [
        'company_name',
        'tax_number',
        'bank_account_number',
        'company_address',
        'shipping_address',
    ]) {
        await page.locator(`#${field}`).fill('Browser company data');
    }

    await page.locator('#country').selectOption({ index: 1 });
    await page.locator('#trade_license').setInputFiles({
        name: 'license.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from(
            '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n',
        ),
    });
    await page.locator('#password').fill('Password123!');
    await page.locator('#password_confirmation').fill('Password123!');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/customers$/);
    const row = page.getByRole('row', { name: /Customer Migration Browser/ });
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.locator('#name')).toHaveValue(
        'Customer Migration Browser',
    );
    await page
        .getByRole('button', { name: 'Add address', exact: true })
        .click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('#address').fill('Browser Street 10');
    await dialog.locator('#phone').fill('31699990001');
    const countryOption = await dialog
        .locator('#country_id option')
        .allTextContents();

    if (countryOption.includes('Netherlands')) {
        await dialog
            .locator('#country_id')
            .selectOption({ label: 'Netherlands' });
    } else {
        await dialog.locator('#country_id').selectOption({ index: 1 });
    }

    await dialog.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(
        page.getByText('Browser Street 10', { exact: false }),
    ).toBeVisible();
    await page.goto('/temp/admin/customers');
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Archive', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Archive', exact: true }).click();
    await expect(row).toHaveCount(0);
    await page.getByRole('checkbox', { name: 'Archived', exact: true }).check();
    await page
        .getByRole('button', { name: 'Apply filters', exact: true })
        .click();
    await expect(
        page.getByRole('row', { name: /Customer Migration Browser/ }),
    ).toBeVisible();
    expect(errors).toEqual([]);
});
