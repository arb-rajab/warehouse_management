import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { seedStoreFixtures } from './support/storeFixtures';

/**
 * The store back office's first Inertia module, staff, beside the Blade
 * pages it shares /temp with (.ai/rules/store-backoffice-migration.md §2.6).
 */
test('dismissing the delete confirmation keeps the staff member, accepting it deletes them', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);

    await page.goto('/temp/admin/staffs/create');
    await page.locator('#name').fill('Delete Candidate');
    await page.locator('#email').fill('delete-candidate@example.com');
    await page.locator('#mobile').fill('0612345678');
    await page.locator('#role_id').selectOption({ label: 'Clerk' });
    await page.locator('#password').fill('Password123!');
    await page.locator('#password_confirmation').fill('Password123!');
    await page.getByRole('button', { name: 'Create staff member' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/staffs$/);

    const row = page.getByRole('row', { name: /Delete Candidate/ });
    const deleteButton = row.getByRole('button', { name: 'Delete' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(row).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(
        page.getByRole('row', { name: /Delete Candidate/ }),
    ).toHaveCount(0);
});

test('the sidebar loads a Blade page in full, and an Inertia redirect to one does too', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'vendor_system_activation'], ['value' => '1']);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'Seller_Commission_Section'], ['value' => '1']);
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs');
    await expect(page.getByRole('heading', { name: 'Staff' })).toBeVisible();

    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
    });
    // Commission settings remain Blade for stage 36.
    await navigation
        .getByRole('button', { name: 'Sellers', exact: true })
        .click();
    const response = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname ===
                '/temp/admin/vendor_commission' &&
            response.request().isNavigationRequest(),
    );
    await navigation
        .getByRole('link', { name: 'Seller commission', exact: true })
        .click();
    expect((await response).status()).toBe(200);
    await expect(page).toHaveURL(/\/temp\/admin\/vendor_commission$/);
    await expect(page.locator('#app')).toHaveCount(0);

    await page.goto('/temp/admin/staffs');
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(/\/temp\/login$/);
    await expect(page.getByRole('button', { name: 'Login' })).toBeVisible();
});
