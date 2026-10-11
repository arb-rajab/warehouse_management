import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('campaign dates keep seconds and picker selection, while native offer and popup confirmations refuse dismissed writes', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/offer_deals/create');
    await page.locator('#title').fill('Browser product offer');
    await expect(page.locator('#start_date')).toHaveCSS('color-scheme', 'dark');
    await page.locator('#start_date').fill('2026-10-01T08:09:11');
    await page.locator('#end_date').fill('2026-10-30T09:08:07');
    await page.locator('#product_search').fill('Campaign browser');
    await expect(
        page.locator('#products option', {
            hasText: 'Campaign browser reward',
        }),
    ).toHaveCount(1);
    await page
        .locator('#base_product_id')
        .selectOption({ label: 'Campaign browser base' });
    await page
        .locator('#products')
        .selectOption({ label: 'Campaign browser reward' });
    const rewardId = await page.locator('#products').inputValue();
    await page.locator(`#discount-${rewardId}`).fill('15');
    await page.locator(`#quantity-${rewardId}`).fill('2');
    await page
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'Upload files', exact: true })
        .click();
    await dialog
        .locator('input[type="file"]')
        .setInputFiles('tests/e2e/fixtures/e2e-pixel.png');
    await dialog
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        dialog.getByRole('button', { name: 'e2e-pixel', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await dialog
        .getByRole('button', { name: 'Use selected files', exact: true })
        .click();
    await expect(page.locator('input[name="banner"]')).not.toHaveValue('');
    await page
        .getByRole('button', { name: 'Create offer', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/offer_deals\/index$/);
    const offer = page.getByRole('row', { name: /Browser product offer/ });
    await expect(offer).toContainText('2026-10-01T08:09:11');
    await expect(offer).toContainText('2026-10-30T09:08:07');
    await offer.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.locator(`#discount-${rewardId}`)).toHaveValue('15');
    await expect(page.locator(`#quantity-${rewardId}`)).toHaveValue('2');
    await expect(page.locator('input[name="banner"]')).not.toHaveValue('');

    await page.goto('/temp/admin/dynamic-popups/create');
    await page.locator('#title').fill('Browser popup');
    await page.locator('#summary').fill('Browser popup summary');
    await page.locator('#btn_text').fill('Open offer');
    await page.locator('#delay_seconds').fill('5');
    await page
        .locator('#offer_id')
        .selectOption({ label: 'Browser product offer' });
    await page.locator('#show_page').selectOption('0');
    await page.getByRole('button', { name: 'Save popup', exact: true }).click();
    await expect(dialog).toBeVisible();
    await expect(page).toHaveURL(/\/dynamic-popups\/create$/);
    await dialog
        .getByRole('button', { name: 'e2e-pixel', exact: true })
        .click();
    await dialog
        .getByRole('button', { name: 'Use selected files', exact: true })
        .click();
    await page.getByRole('button', { name: 'Save popup', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/dynamic-popups$/);
    const popup = page.getByRole('row', { name: /Browser popup/ });
    page.once('dialog', (d) => d.dismiss());
    await popup.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(
        popup.getByRole('button', { name: 'Publish', exact: true }),
    ).toBeVisible();
    page.once('dialog', (d) => d.accept());
    await popup.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(
        popup.getByRole('button', { name: 'Hide', exact: true }),
    ).toBeVisible();

    await page.goto('/temp/admin/offer_deals/index');
    page.once('dialog', (d) => d.dismiss());
    await offer.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(offer).toBeVisible();
    page.once('dialog', (d) => d.accept());
    await offer.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(offer).toHaveCount(0);
});

test('flash-deal dates render native dark controls with second precision', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/flash_deals/create');
    await expect(page.locator('#start_date')).toHaveCSS('color-scheme', 'dark');
    await expect(page.locator('#end_date')).toHaveCSS('color-scheme', 'dark');
    await page.locator('#title').fill('Browser flash deal');
    await page.locator('#start_date').fill('2026-10-01T08:09:11');
    await page.locator('#end_date').fill('2026-10-30T09:08:07');
    await page.locator('#product_search').fill('Campaign browser');
    await expect(
        page.locator('#products option', {
            hasText: 'Campaign browser reward',
        }),
    ).toHaveCount(1);
    await page
        .locator('#products')
        .selectOption({ label: 'Campaign browser reward' });
    const rewardId = await page.locator('#products').inputValue();
    await page.locator(`#discount-${rewardId}`).fill('25');
    await page
        .getByRole('button', { name: 'Create flash deal', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/flash_deals$/);
    const deal = page.getByRole('row', { name: /Browser flash deal/ });
    await deal.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.locator('#start_date')).toHaveValue(
        '2026-10-01T08:09:11',
    );
    await expect(page.locator('#end_date')).toHaveValue('2026-10-30T09:08:07');
    await expect(page.locator(`#discount-${rewardId}`)).toHaveValue('25');
});
