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

test('seller profile focus native confirmations and RTL work under strict CSP', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        // Keep unrelated dashboard Faker thumbnails local under the strict image policy.
        App\Models\Product::query()->update(['thumbnail_img' => null]);
        for ($i = 0; $i < 23; $i++) {
            $user = App\Models\StoreUser::forceCreate(['name' => 'Browser seller '.$i, 'email' => 'browser-seller-'.$i.'@example.com', 'password' => 'password', 'user_type' => 'seller', 'email_verified_at' => now(), 'balance' => 0]);
            App\Models\Shop::forceCreate(['user_id' => $user->id, 'name' => $i === 22 ? 'Browser seller shop' : 'Other seller '.$i, 'slug' => 'browser-seller-'.$i, 'address' => 'Shop street', 'bank_name' => '<script>seller bank</script>', 'verification_info' => '[]']);
        }
        foreach (['vendor_system_activation', 'Payouts_Section'] as $key) {
            $setting = App\Models\BusinessSetting::where('type', $key)->first() ?? new App\Models\BusinessSetting;
            $setting->type = $key;
            $setting->value = '1';
            $setting->save();
        }
    `);
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/sellers');
    const row = page
        .locator('main tbody tr')
        .filter({ hasText: 'Browser seller shop' });
    await row
        .getByRole('button', { name: 'Seller profile', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Seller profile' });
    await expect(dialog).toContainText('Browser seller 22');
    await expect(dialog).toContainText('<script>seller bank</script>');
    await expect(dialog.locator('script')).toHaveCount(0);
    await expect(
        dialog.getByRole('button', { name: 'Close', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(
        dialog.getByRole('link', { name: 'Browser seller shop' }),
    ).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(
        row.getByRole('button', { name: 'Seller profile', exact: true }),
    ).toBeFocused();

    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Do you really want to ban this seller?');
        await dialog.dismiss();
    });
    await row.getByRole('button', { name: 'Ban seller', exact: true }).click();
    await expect(
        row.getByRole('button', { name: 'Ban seller', exact: true }),
    ).toBeVisible();
    page.once('dialog', async (dialog) => {
        await dialog.accept();
    });
    await row.getByRole('button', { name: 'Ban seller', exact: true }).click();
    await expect(
        row.getByRole('button', { name: 'Unban seller', exact: true }),
    ).toBeVisible();
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toContain('Delete Browser seller shop?');
        await dialog.dismiss();
    });
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    const loginArrow = row.locator('a[href*="/sellers/login/"] svg');
    await expect(loginArrow).toHaveCSS('rotate', 'none');
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(loginArrow).toHaveCSS('rotate', '180deg');
    await expect(page.locator('main h1')).toHaveText('كل البائعين');
    await page.emulateMedia({ colorScheme: 'dark' });
    const colors = await colorsOf(page, 'main input[type="search"]');
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
    expect(errors).toEqual([]);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);

    // The list's native CSRF form must still reach its stage-35 Blade payment screen.
    const paymentForm = row.locator('form[action$="/sellers/payment_modal"]');
    const shopId = await paymentForm.locator('input[name="id"]').inputValue();
    const paymentResponse = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname ===
                '/temp/admin/sellers/payment_modal' &&
            response.request().isNavigationRequest(),
    );
    await paymentForm.getByRole('button').click();
    expect((await paymentResponse).status()).toBe(200);
    await expect(page.locator('#app')).toHaveCount(0);
    await expect(page.locator('input[name="shop_id"]')).toHaveValue(shopId);
});
