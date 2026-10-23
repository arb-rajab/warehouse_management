import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    colorsOf,
    documentViolations,
    expectStrictPolicy,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

test('verification builder adds reorders removes saves and reloads in RTL and dark mode', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        App\Models\Product::query()->update(['thumbnail_img' => null]);
        Illuminate\Support\Facades\DB::table('business_settings')->where('type', 'verification_form')->delete();
    `);
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/verification/form');
    await expect(
        page.getByText('No verification fields. Save to keep an empty form.'),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Add field', exact: true }).click();
    await page
        .getByRole('textbox', { name: 'Field 1 label required', exact: true })
        .fill('Company');
    await page.getByRole('button', { name: 'Add field', exact: true }).click();
    await page
        .getByLabel('Field 2 type', { exact: true })
        .selectOption('multi_select');
    await page
        .getByRole('textbox', { name: 'Field 2 label required', exact: true })
        .fill('Markets');
    await page
        .getByRole('textbox', {
            name: 'Field 2, option 1 required',
            exact: true,
        })
        .fill('Retail');
    await page
        .getByRole('button', { name: 'Add option to field 2', exact: true })
        .click();
    await page
        .getByRole('textbox', {
            name: 'Field 2, option 2 required',
            exact: true,
        })
        .fill('Wholesale');
    await page.getByRole('button', { name: 'Add field', exact: true }).click();
    await page
        .getByRole('textbox', { name: 'Field 3 label required', exact: true })
        .fill('Remove me');
    await page
        .getByRole('button', { name: 'Remove field 3', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Move field 2 up', exact: true })
        .click();
    await expect(
        page.getByRole('textbox', {
            name: 'Field 1 label required',
            exact: true,
        }),
    ).toHaveValue('Markets');
    await expect(
        page.getByRole('button', { name: 'Move field 1 up', exact: true }),
    ).toBeDisabled();
    const save = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname ===
                '/temp/admin/verification/form',
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await save).status()).toBe(302);
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(
        page.getByRole('textbox', {
            name: 'Field 1 label required',
            exact: true,
        }),
    ).toHaveValue('Markets');
    await expect(
        page.getByRole('textbox', {
            name: 'Field 1, option 2 required',
            exact: true,
        }),
    ).toHaveValue('Wholesale');
    await expect(
        page.getByRole('textbox', {
            name: 'Field 2 label required',
            exact: true,
        }),
    ).toHaveValue('Company');
    seedStoreFixtures(String.raw`
        $json = Illuminate\Support\Facades\DB::table('business_settings')->where('type', 'verification_form')->value('value');
        $expected = '[{"type":"multi_select","label":"Markets","options":"[\\"Retail\\",\\"Wholesale\\"]"},{"type":"text","label":"Company"}]';
        if ($json !== $expected) { throw new RuntimeException('Stored form bytes differ from the legacy contract'); }
    `);
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('main h1')).toHaveText('نموذج التحقق من البائع');

    for (const selector of [
        'main fieldset:first-of-type input[id^="label-"]',
        'main fieldset:first-of-type select',
    ]) {
        const colors = await colorsOf(page, selector);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    await page
        .getByRole('button', { name: 'نقل الحقل 1 للأسفل', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'إزالة الحقل 2', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'إزالة الحقل 1', exact: true })
        .click();
    const emptySave = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname ===
                '/temp/admin/verification/form',
    );
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    expect((await emptySave).status()).toBe(302);
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.locator('[data-verification-field]')).toHaveCount(0);
    seedStoreFixtures(
        String.raw`if (get_setting('verification_form') !== '[]') { throw new RuntimeException('Empty form was not saved'); }`,
    );
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    expect(errors).toEqual([]);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});

test('commission settings retain numeric boundaries and the two compatibility switches', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        App\Models\Product::query()->update(['thumbnail_img' => null]);
        App\Models\StoreUser::query()->where('email', 'store-admin@example.com')->update(['raizer_admin' => 1]);
        foreach (['vendor_commission' => '5', 'minimum_seller_amount_withdraw' => '10', 'vendor_commission_activation' => '0', 'category_wise_commission' => '0', 'vendor_system_activation' => '1', 'Seller_Commission_Section' => '1', 'Seller_Verify_Form_Section' => '1'] as $type => $value) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $type], ['value' => $value]);
        }
    `);
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/vendor_commission');
    await page
        .getByRole('spinbutton', {
            name: 'Seller commission (%) required',
            exact: true,
        })
        .fill('100.01');
    expect(
        await page
            .locator('#vendor_commission')
            .evaluate((element: HTMLInputElement) => element.checkValidity()),
    ).toBe(false);
    await page.locator('#vendor_commission').fill('99.99');
    await page.locator('#minimum_seller_amount_withdraw').fill('0');
    const save = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname ===
                '/temp/admin/vendor_commission_update',
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await save).status()).toBe(302);
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.locator('#vendor_commission')).toHaveValue('99.99');
    await expect(page.locator('#minimum_seller_amount_withdraw')).toHaveValue(
        '0',
    );

    for (const key of [
        'vendor_commission_activation',
        'category_wise_commission',
    ]) {
        const activation = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname ===
                '/temp/admin/business-settings/update/seller-commission-activation',
        );
        await page.locator(`#${key}`).selectOption('1');
        const response = await activation;
        expect(response.status()).toBe(200);
        expect(await response.text()).toBe('1');
        expect(response.request().postDataJSON()).toEqual({
            type: key,
            value: 1,
        });
        await expect(page.getByRole('status')).toHaveText(
            'Activation setting saved.',
        );
    }

    await page.reload();
    await expect(page.locator('#vendor_commission_activation')).toHaveValue(
        '1',
    );
    await expect(page.locator('#category_wise_commission')).toHaveValue('1');
    await page.emulateMedia({ colorScheme: 'dark' });
    const colors = await colorsOf(page, '#vendor_commission');
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
    });
    const sellersMenu = navigation.getByRole('button', {
        name: 'Sellers',
        exact: true,
    });

    if ((await sellersMenu.getAttribute('aria-expanded')) === 'false') {
        await sellersMenu.click();
    }

    const visit = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname ===
                '/temp/admin/verification/form' &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'Seller verification form', exact: true })
        .click();
    expect((await visit).status()).toBe(200);
    await expect(page.locator('main h1')).toHaveText(
        'Seller verification form',
    );
    expect(reports).toEqual([]);
});
