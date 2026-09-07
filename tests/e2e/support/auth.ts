import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('#email').fill('test@example.com');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
}
