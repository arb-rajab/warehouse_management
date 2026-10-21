import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { seedStoreFixtures } from './support/storeFixtures';

test('customer notification deletion confirms before writing and preserves the other notifications', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        $user = App\Models\StoreUser::where('email', 'store-admin@example.com')->firstOrFail();
        foreach (['Delete notification candidate', 'Keep notification candidate'] as $title) {
            App\Models\Notification::create(['user_id' => $user->id, 'title' => $title, 'body' => 'Test body', 'status' => 'sent', 'response' => '{}']);
        }
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/notifications');
    const row = page.getByRole('row', {
        name: /Delete notification candidate/,
    });
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    await expect(
        page.getByRole('row', { name: /Keep notification candidate/ }),
    ).toBeVisible();
});

test('the notification form rejects empty fields natively and keeps its Inertia list navigation', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/notifications/create');
    const title = page.getByRole('textbox', {
        name: 'Title required',
        exact: true,
    });
    await page
        .getByRole('button', { name: 'Send notification', exact: true })
        .click();
    await expect(title).toBeFocused();
    await expect(page).toHaveURL(/\/temp\/admin\/notifications\/create$/);
    await page.getByRole('link', { name: 'Cancel', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/notifications$/);
    await expect(
        page.getByRole('heading', {
            name: 'Customer notifications',
            exact: true,
        }),
    ).toBeVisible();
});
