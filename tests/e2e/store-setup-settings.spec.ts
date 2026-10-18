import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

/**
 * General settings moved to Inertia with a typed writer
 * (.ai/rules/store-backoffice-migration.md §17). The timezone stays
 * config-managed, and the Blade admin shell keeps reading the saved logo.
 */
function resetGeneralSettings(): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->whereIn('type', ['site_name', 'system_logo_white', 'system_logo_black', 'admin_login_background', 'timezone'])->delete();
        Illuminate\Support\Facades\DB::table('business_settings')->insert(['type' => 'site_name', 'value' => 'Browser store']);
        foreach (['Setup_configuration_section', 'website_setup', 'website_setup_footer'] as $key) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $key, 'lang' => null], ['value' => '1']);
        }
    `);
}

test('general settings round-trip the system name and a canonical logo pick through a reload', async ({
    page,
}) => {
    resetGeneralSettings();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page.locator('#upload-files').setInputFiles({
        name: 'system-logo.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'system-logo', exact: true }),
    ).toBeVisible();

    const response = await page.goto('/temp/admin/general-setting');
    const scriptSrc = response
        ?.headers()
        ['content-security-policy']?.split(';')
        .map((directive) => directive.trim())
        .find((directive) => directive.startsWith('script-src '));
    expect(scriptSrc).toBe("script-src 'self'");
    await expect(
        page.getByRole('heading', { name: 'General settings', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#site_name')).toHaveValue('Browser store');
    await expect(page.locator('select#timezone')).toHaveValue('UTC');
    await page.locator('#site_name').fill('Browser store renamed');
    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .first()
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'system-logo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    const white = page.locator('input[name="settings[system_logo_white]"]');
    const logo = await white.inputValue();
    expect(logo).toMatch(/^\d+$/);
    await expect(
        page.locator('input[name="settings[system_logo_black]"]'),
    ).toHaveValue('');

    const saved = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/general-setting' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    expect((await saved).status()).toBe(302);
    await page.reload();
    await expect(page.locator('#site_name')).toHaveValue(
        'Browser store renamed',
    );
    await expect(white).toHaveValue(logo);
    await expect(
        page.getByRole('img', { name: 'system-logo', exact: true }),
    ).toBeVisible();
    await expect(page.locator('select#timezone')).toHaveValue('UTC');
    seedStoreFixtures(String.raw`
        if (get_setting('site_name') !== 'Browser store renamed') { throw new RuntimeException('Site name was not saved'); }
        if (get_setting('system_logo_white') !== '${logo}') { throw new RuntimeException('Logo id was not saved as its string'); }
        if (get_setting('system_logo_black') !== null || get_setting('admin_login_background') !== null) { throw new RuntimeException('Empty logos were not cleared'); }
        if (Illuminate\Support\Facades\DB::table('business_settings')->where('type', 'timezone')->exists()) { throw new RuntimeException('Timezone was stored'); }
    `);

    // Payment histories remain a Blade page in the admin shell.
    await page.goto('/temp/admin/seller/payments');
    await expect(page.locator('#app')).toHaveCount(0);
    const shellLogo = page.locator('.aiz-side-nav-logo-wrap img').first();
    await expect(shellLogo).toHaveAttribute('alt', 'Browser store renamed');
    await expect(shellLogo).toHaveAttribute('src', /\/uploads\/all\//);
    await expect
        .poll(() =>
            shellLogo.evaluate((img: HTMLImageElement) => img.naturalWidth),
        )
        .toBeGreaterThan(0);
});

test('a refused timezone keeps the draft, in Arabic RTL with dark contrast', async ({
    page,
}) => {
    resetGeneralSettings();
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/general-setting');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'الإعدادات العامة', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('اسم النظام', { exact: true })).toBeVisible();
    await page.locator('#site_name').fill('x'.repeat(300));
    await expect(page.locator('#site_name')).toHaveValue('x'.repeat(255));
    await page.locator('#site_name').fill('Refused draft name');
    await page.locator('select#timezone').selectOption('Asia/Damascus');

    const refused = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/general-setting' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    expect((await refused).status()).toBe(302);
    await expect(
        page.getByText(
            'المنطقة الزمنية محددة في إعدادات الخادم ولا يمكن تغييرها من هنا.',
            { exact: true },
        ),
    ).toBeVisible();
    await expect(page.locator('#site_name')).toHaveValue('Refused draft name');
    await expect(page.locator('select#timezone')).toHaveValue('Asia/Damascus');
    seedStoreFixtures(String.raw`
        if (get_setting('site_name') !== 'Browser store') { throw new RuntimeException('Refused write changed the site name'); }
        if (Illuminate\Support\Facades\DB::table('business_settings')->where('type', 'timezone')->exists()) { throw new RuntimeException('Refused timezone was stored'); }
    `);

    for (const selector of ['#site_name', 'select#timezone']) {
        const colors = await page.locator(selector).evaluate((input) => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            })!;
            const rgb = (color: string) => {
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);

                return Array.from(context.getImageData(0, 0, 1, 1).data).slice(
                    0,
                    3,
                ) as [number, number, number];
            };
            const style = getComputedStyle(input);

            return {
                foreground: rgb(style.color),
                background: rgb(style.backgroundColor),
            };
        });
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(
        page.getByRole('heading', { name: 'General settings', exact: true }),
    ).toBeVisible();
});

test('the sidebar visits general settings with Inertia', async ({ page }) => {
    resetGeneralSettings();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs');
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
        exact: true,
    });
    await navigation
        .getByRole('button', { name: 'Setup & configurations', exact: true })
        .click();
    const response = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/general-setting' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'General settings', exact: true })
        .click();
    expect((await response).status()).toBe(200);
    await expect(page).toHaveURL(/\/temp\/admin\/general-setting$/);
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'General settings', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#site_name')).toHaveValue('Browser store');
});
