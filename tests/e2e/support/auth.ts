import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.locator('#email').fill('test@example.com');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

/**
 * Sign in to the store back office as the seeded Super Admin
 * (database/seeders/StoreUserSeeder.php), through the store's own Blade
 * login page, which lands on the Blade dashboard.
 */
export async function loginAsStoreAdmin(page: Page): Promise<void> {
    await page.goto('/temp/login');
    // The storefront's header carries a second, hidden login form.
    const form = page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: 'Login' }) });
    await form.locator('#email').fill('store-admin@example.com');
    await form.locator('#password').fill('password');
    await form.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/temp\/admin$/);
}
