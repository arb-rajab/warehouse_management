import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('dismissing the logout confirmation stays signed in, accepting it logs out', async ({
    page,
}) => {
    await loginAsAdmin(page);

    const logoutButton = page.getByRole('button', { name: 'Log out' });

    page.once('dialog', (dialog) => dialog.dismiss());
    await logoutButton.click();
    await expect(page).toHaveURL(/\/admin\/rows$/);

    page.once('dialog', (dialog) => dialog.accept());
    await logoutButton.click();
    await expect(page).toHaveURL(/\/login$/);
});
