import type { Page } from '@playwright/test';

/**
 * The language switcher and logout action live inside AdminLayout's
 * AccountMenu dropdown (not as always-visible buttons), so any e2e spec
 * that needs to switch locale or log out has to open it first.
 */
export async function openAccountMenu(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Account' }).click();
}

/** Wait for LanguageSwitcher's full reload before starting another visit. */
export async function switchLocale(page: Page, label: string): Promise<void> {
    await Promise.all([
        page.waitForEvent('load'),
        page.getByRole('button', { name: label, exact: true }).click(),
    ]);
}
