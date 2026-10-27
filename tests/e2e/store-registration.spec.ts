import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

for (const [url, businessFields] of [
    ['/temp/register', true],
    ['/temp/register', false],
    ['/temp/users/registration', false],
] as const) {
    test(`${url} registers through the terms agreement with business fields ${businessFields ? 'filled' : 'omitted'}`, async ({
        page,
    }, testInfo) => {
        const email = `registration-${Date.now()}@example.com`;
        const license = '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n';
        seedStoreFixtures(String.raw`
            Illuminate\Support\Facades\DB::table('countries')->updateOrInsert(
                ['code' => 'NL'], ['name' => 'Netherlands', 'status' => 1]
            );
        `);
        await page.goto(url);
        const form = page.locator('#reg-form');
        await expect(form.locator('[name="country_code"]')).toHaveCount(0);
        await expect(form.locator('[name="shipping_address"]')).toHaveCount(0);

        for (const [name, value] of Object.entries({
            name: 'Browser Registration',
            company_name: 'Browser Company',
            email,
            phone: '612345678',
            password: 'registration-password',
            password_confirmation: 'registration-password',
        })) {
            await form.locator(`input[name="${name}"]`).fill(value);
        }

        await form
            .locator('select[name="country"]')
            .selectOption('Netherlands');

        if (businessFields) {
            await expect(form).toHaveAttribute(
                'enctype',
                'multipart/form-data',
            );
            await page
                .getByLabel('Tax Number (optional)', { exact: true })
                .fill('NL-BROWSER');
            await page
                .getByLabel('Company Address (optional)', { exact: true })
                .fill('Browser address');
            await page
                .getByLabel('Trade License (optional)', { exact: true })
                .setInputFiles({
                    name: 'license.pdf',
                    mimeType: 'application/pdf',
                    buffer: Buffer.from(license),
                });
        }

        await form
            .getByRole('link', { name: 'Create an account', exact: true })
            .click();
        await expect(page.locator('#terms_conditions_modal')).toBeVisible();
        await Promise.all([
            page.waitForURL(/\/temp\/?$/),
            page.getByRole('button', { name: 'Agree', exact: true }).click(),
        ]);

        const result = testInfo.outputPath('registered-customer.json');
        seedStoreFixtures(String.raw`
            $user = App\Models\StoreUser::where('email', ${php(email)})->sole();
            file_put_contents(${php(result)}, json_encode([
                'tax_number' => $user->tax_number,
                'company_address' => $user->company_address,
                'license' => $user->trade_license ? Illuminate\Support\Facades\Storage::disk('local')->get('uploads/licenses/'.$user->trade_license) : null,
                'shipping_address' => $user->shipping_address,
                'addresses' => $user->addresses()->count(),
            ]));
        `);
        expect(JSON.parse(readFileSync(result, 'utf8'))).toEqual({
            tax_number: businessFields ? 'NL-BROWSER' : null,
            company_address: businessFields ? 'Browser address' : null,
            license: businessFields ? license : null,
            shipping_address: null,
            addresses: 1,
        });
    });
}
