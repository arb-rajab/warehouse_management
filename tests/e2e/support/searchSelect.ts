import type { Locator, Page } from '@playwright/test';

/**
 * Opens a StoreSearchSelect, types into the search box of its panel and picks
 * the first row whose accessible name matches. A single select closes itself.
 */
export async function pickSearchOption(
    page: Page,
    trigger: string | Locator,
    query: string,
    option: string | RegExp,
): Promise<void> {
    const button =
        typeof trigger === 'string' ? page.locator(trigger) : trigger;

    await button.click();
    await page.locator('input[role="combobox"]').fill(query);
    await page.getByRole('option', { name: option }).first().click();
}

/** Picks several rows of a multiple StoreSearchSelect, then closes its panel. */
export async function pickSearchOptions(
    page: Page,
    trigger: string | Locator,
    query: string,
    options: (string | RegExp)[],
): Promise<void> {
    const button =
        typeof trigger === 'string' ? page.locator(trigger) : trigger;

    await button.click();
    await page.locator('input[role="combobox"]').fill(query);

    for (const option of options) {
        await page.getByRole('option', { name: option }).first().click();
    }

    await page.keyboard.press('Escape');
}
