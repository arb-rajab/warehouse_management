import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Wait for LanguageSwitcher's full reload before starting another visit.
 * From 640px the switcher sits in the back-office header, always visible;
 * on a phone it is in the sidebar drawer, so open "Open menu" first.
 *
 * The shell renders a second switcher in the sidebar that `sm:hidden` hides
 * from 640px. On a loaded machine the page can be interactive before that
 * rule applies, so both buttons are visible for a moment and a strict click
 * fails; wait until exactly one is visible.
 */
export async function switchLocale(page: Page, label: string): Promise<void> {
    const button = page.getByRole('button', { name: label, exact: true });

    await expect(button).toHaveCount(1);
    await Promise.all([page.waitForEvent('load'), button.click()]);
}
