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

test('seller payment and withdrawal dialogs retain native constraints confirmation focus RTL and strict CSP', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedStoreFixtures(String.raw`
        App\Models\Product::query()->update(['thumbnail_img' => null]);
        $seller = App\Models\StoreUser::forceCreate(['name' => 'Payout Browser Seller', 'email' => 'payout-browser@example.com', 'password' => 'password', 'user_type' => 'seller', 'email_verified_at' => now(), 'balance' => 77]);
        App\Models\Shop::forceCreate(['user_id' => $seller->id, 'name' => 'Payout Browser Shop', 'slug' => 'payout-browser-shop', 'admin_to_pay' => 100, 'cash_on_delivery_status' => 1, 'bank_payment_status' => 1, 'bank_name' => '<script>bank text</script>', 'bank_acc_name' => 'Seller account', 'bank_acc_no' => '123', 'bank_routing_no' => 456]);
        App\Models\SellerWithdrawRequest::forceCreate(['user_id' => $seller->id, 'amount' => 40, 'message' => '<script>withdraw text</script>', 'status' => 0, 'viewed' => 0]);
        foreach (['vendor_system_activation', 'Payouts_Section', 'Payout_request_Section'] as $key) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $key], ['value' => '1']);
        }
    `);
    await loginAsStoreAdmin(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const reports = await watchCsp(page);
    await expectStrictPolicy(page, '/temp/admin/sellers');
    const sellerRow = page
        .locator('main tbody tr')
        .filter({ hasText: 'Payout Browser Shop' });
    const entry = sellerRow.getByRole('button', {
        name: 'Go to payment',
        exact: true,
    });
    await entry.click();
    const payment = page.getByRole('dialog', { name: 'Go to payment' });
    await expect(payment.locator('input[name="amount"]')).toHaveValue('100');
    await expect(payment).toContainText('<script>bank text</script>');
    await expect(payment.locator('script')).toHaveCount(0);
    await payment.locator('input[name="amount"]').fill('0');
    expect(
        await payment
            .locator('input[name="amount"]')
            .evaluate((input: HTMLInputElement) => input.checkValidity()),
    ).toBe(false);
    await payment.locator('input[name="amount"]').fill('25');
    await payment
        .locator('select[name="payment_option"]')
        .selectOption('bank_payment');
    await payment.locator('input[name="txn_code"]').fill('BROWSER-25');
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toBe('Record this seller payment?');
        await dialog.dismiss();
    });
    await payment.getByRole('button', { name: 'Pay', exact: true }).click();
    await expect(payment).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await payment.getByRole('button', { name: 'Pay', exact: true }).click();
    await expect(payment).not.toBeVisible();
    await sellerRow
        .getByRole('link', { name: 'Payment history', exact: true })
        .click();
    await expect(page.locator('main')).toContainText('BROWSER-25');
    await expect(page.locator('main')).toContainText('Bank payment');
    await expect(page.locator('#app')).toHaveCount(1);

    await page.goto('/temp/admin/withdraw_requests_all');
    const row = page
        .locator('main tbody tr')
        .filter({ hasText: 'Payout Browser Shop' });
    await row
        .getByRole('button', { name: 'View Message', exact: true })
        .click();
    const message = page.getByRole('dialog', { name: 'Seller Message' });
    await expect(message).toContainText('<script>withdraw text</script>');
    await expect(message.locator('textarea,script')).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(
        row.getByRole('button', { name: 'View Message', exact: true }),
    ).toBeFocused();
    await row.getByRole('button', { name: 'Pay Now', exact: true }).click();
    await expect(payment.locator('input[name="amount"]')).toHaveValue('40');
    await payment.locator('select').selectOption('cash');
    await expect(payment.locator('input[name="txn_code"]')).toHaveCount(0);
    page.once('dialog', (dialog) => dialog.accept());
    await payment.getByRole('button', { name: 'Pay', exact: true }).click();
    await expect(payment).not.toBeVisible();
    await expect(row).toContainText('Paid');
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await row.getByRole('button', { name: 'ادفع الآن', exact: true }).click();
    const arabicDialog = page.getByRole('dialog', {
        name: 'الانتقال إلى الدفع',
    });
    await expect(arabicDialog.locator('input[name="amount"]')).toBeVisible();
    await page.emulateMedia({ colorScheme: 'dark' });
    const colors = await colorsOf(page, '[role="dialog"] input[name="amount"]');
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
    expect(errors).toEqual([]);
    expect(reports).toEqual([]);
    expect(await documentViolations(page)).toEqual([]);
});
