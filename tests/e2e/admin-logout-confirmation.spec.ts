import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('dismissing the logout confirmation stays signed in, accepting it logs out', async ({
    page,
}) => {
    await loginAsAdmin(page);

    // The logout button sits in the sidebar footer, always visible from lg.
    const logoutButton = page
        .locator('#admin-sidebar')
        .getByRole('button', { name: 'Log out' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await logoutButton.click();
    await expect(page).toHaveURL(/\/admin$/);

    page.once('dialog', (dialog) => dialog.accept());
    await logoutButton.click();
    await expect(page).toHaveURL(/\/admin\/login$/);
});
