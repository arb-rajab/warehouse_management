import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
test('dashboard charts, menu search and notifications coexist with Blade pages', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await expect(
        page.getByRole('heading', { name: 'Dashboard', exact: true }),
    ).toBeVisible();
    await expect(page.locator('canvas')).toHaveCount(4);
    await expect(
        page.getByRole('heading', { name: 'Top 12 Products' }),
    ).toBeVisible();
    await page.getByLabel('Search menu').fill('orders');
    await expect(
        page
            .getByRole('navigation')
            .getByRole('link', { name: 'All orders', exact: true }),
    ).toBeVisible();
    await page.getByLabel('Search menu').fill('');
    await page
        .getByRole('button', { name: 'Notifications', exact: true })
        .click();
    await expect(page.getByText('No notifications found')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByText('No notifications found')).toBeHidden();
    await page.getByRole('button', { name: 'Sales', exact: true }).click();
    await page
        .getByRole('navigation')
        .getByRole('link', { name: 'All orders', exact: true })
        .click();
    await expect(page.locator('#app')).toHaveCount(1);
    const popupPromise = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'Browse website' }).click();
    const storefront = await popupPromise;
    await storefront.waitForLoadState('domcontentloaded');
    await expect(storefront.locator('#app')).toHaveCount(0);
    await storefront.close();
});
test('async order entry enforces native fields, calculates totals, retains server errors, edits and cancels', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page).toHaveURL(/\/create$/);
    expect(
        await page
            .locator('#customer_id')
            .evaluate(
                (element: HTMLSelectElement) => element.validity.valueMissing,
            ),
    ).toBe(true);
    await page.locator('#customer_id-search').fill('Browser Buyer');
    await expect(
        page
            .locator('#customer_id option')
            .filter({ hasText: 'Browser Buyer' }),
    ).toHaveCount(1);
    await page.locator('#customer_id').selectOption({ label: 'Browser Buyer' });
    await page.locator('#product-1-search').fill('BROWSER-ORDER');
    await expect(
        page
            .locator('#product-1 option')
            .filter({ hasText: 'Browser Order Product' }),
    ).toHaveCount(1);
    await page
        .locator('#product-1')
        .selectOption({ label: 'Browser Order Product BROWSER-ORDER' });
    await expect(page.locator('#price-1')).toHaveValue('12');
    await page.locator('#quantity-1').fill('2');
    await expect(page.getByText('29.04', { exact: true })).toBeVisible();
    await page.locator('#quantity-1').fill('999');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'stock' }).first(),
    ).toBeVisible();
    await expect(page.locator('#quantity-1')).toHaveValue('999');
    await page.locator('#quantity-1').fill('2');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page).toHaveURL(/\/orders\/\d+\/show$/);
    await page.getByRole('link', { name: 'Edit order' }).click();
    await page.locator('#quantity-1').fill('1');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page.getByText('17.04', { exact: true })).toBeVisible();
    await page.locator('#delivery').selectOption('cancelled');
    await page
        .locator('form')
        .filter({ has: page.locator('#delivery') })
        .getByRole('button', { name: 'Save order', exact: true })
        .click();
    await expect(page.locator('#delivery')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Edit order' })).toHaveCount(0);
});

test('order fields remain readable in dark mode and the store layout follows Arabic RTL', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    const styles = await page
        .locator('#customer_id-search')
        .evaluate((element) => {
            const style = getComputedStyle(element);
            const rgb = (color: string) => {
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 1;
                const context = canvas.getContext('2d')!;
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);

                return Array.from(
                    context.getImageData(0, 0, 1, 1).data.slice(0, 3),
                );
            };

            return {
                foreground: rgb(style.color),
                background: rgb(style.backgroundColor),
            };
        });
    expect(
        contrastRatio(styles.foreground, styles.background),
    ).toBeGreaterThanOrEqual(4.5);
    await page.getByRole('button', { name: 'Arabic', exact: true }).click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'إنشاء طلب شحن' }),
    ).toBeVisible();
});

test('representative search applies the discount and piece conversion before tax', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/order/create');
    await page.locator('#customer_id-search').fill('Browser Buyer');
    await expect(
        page
            .locator('#customer_id option')
            .filter({ hasText: 'Browser Buyer' }),
    ).toHaveCount(1);
    await page.locator('#customer_id').selectOption({ label: 'Browser Buyer' });
    await page.locator('#rep_id-search').fill('Browser Representative');
    await expect(
        page
            .locator('#rep_id option')
            .filter({ hasText: 'Browser Representative' }),
    ).toHaveCount(1);
    await page
        .locator('#rep_id')
        .selectOption({ label: 'Browser Representative' });
    await page.locator('#product-1-search').fill('BROWSER-ORDER');
    await expect(
        page
            .locator('#product-1 option')
            .filter({ hasText: 'Browser Order Product' }),
    ).toHaveCount(1);
    await page
        .locator('#product-1')
        .selectOption({ label: 'Browser Order Product BROWSER-ORDER' });
    await expect(page.locator('#price-1')).toHaveValue('10.8');
    await page.locator('#unit-1').selectOption('PerPiece');
    await expect(page.locator('#price-1')).toHaveValue('1.8');
    await page.locator('#quantity-1').fill('3');
    await expect(page.getByText('6.53', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page).toHaveURL(/\/orders\/\d+\/show$/);
    await expect(
        page.getByText('Browser Representative', { exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit order' })).toHaveCount(0);
});
