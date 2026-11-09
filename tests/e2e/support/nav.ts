import type { Page } from '@playwright/test';

/**
 * Wait for LanguageSwitcher's full reload before starting another visit.
 * From 640px the switcher sits in the back-office header, always visible;
 * on a phone it is in the sidebar drawer, so open "Open menu" first.
 */
export async function switchLocale(page: Page, label: string): Promise<void> {
    await Promise.all([
        page.waitForEvent('load'),
        page.getByRole('button', { name: label, exact: true }).click(),
    ]);
}
