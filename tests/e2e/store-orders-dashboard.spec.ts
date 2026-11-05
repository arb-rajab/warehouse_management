import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import { pickSearchOption } from './support/searchSelect';
test('async order entry enforces native fields, calculates totals, retains server errors, edits and cancels', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/new/order/trip/create');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page).toHaveURL(/\/create$/);
    // The native required guard blocks the submit and hands focus to the trigger.
    expect(
        await page
            .locator('[data-testid="search-select-guard"]')
            .first()
            .evaluate(
                (element: HTMLInputElement) => element.validity.valueMissing,
            ),
    ).toBe(true);
    await expect(page.locator('#customer_id')).toBeFocused();
    await pickSearchOption(
        page,
        '#customer_id',
        'Browser Buyer',
        /Browser Buyer/,
    );
    await expect(page.locator('#customer_id')).toContainText('Browser Buyer');
    await pickSearchOption(
        page,
        '#product-1',
        'BROWSER-ORDER',
        /Browser Order Product/,
    );
    await expect(page.locator('#product-1')).toContainText(
        'Browser Order Product',
    );
    await expect(page.locator('#price-1')).toHaveValue('12');
    await page.locator('#quantity-1').fill('2');
    await expect(
        page
            .locator('dt')
            .filter({ hasText: /^Total$/ })
            .locator('xpath=following-sibling::dd[1]'),
    ).toHaveText('24.00');
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
    await expect(
        page
            .locator('dt')
            .filter({ hasText: /^Total$/ })
            .locator('xpath=following-sibling::dd[1]'),
    ).toHaveText('12.00');
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
    const styles = await page.locator('#customer_id').evaluate((element) => {
        // The trigger's placeholder text on the trigger's own background.
        const style = getComputedStyle(element.firstElementChild!);
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
            background: rgb(getComputedStyle(element).backgroundColor),
        };
    });
    expect(
        contrastRatio(styles.foreground, styles.background),
    ).toBeGreaterThanOrEqual(4.5);
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'إنشاء طلب شحن' }),
    ).toBeVisible();
});

test('representative search applies the discount and piece conversion with the established total', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/feat/order/create');
    await pickSearchOption(
        page,
        '#customer_id',
        'Browser Buyer',
        /Browser Buyer/,
    );
    await pickSearchOption(
        page,
        '#rep_id',
        'Browser Representative',
        /Browser Representative/,
    );
    await pickSearchOption(
        page,
        '#product-1',
        'BROWSER-ORDER',
        /Browser Order Product/,
    );
    await expect(page.locator('#price-1')).toHaveValue('10.8');
    await page.locator('#unit-1').selectOption('PerPiece');
    await expect(page.locator('#price-1')).toHaveValue('1.8');
    await page.locator('#quantity-1').fill('3');
    await expect(
        page
            .locator('dt')
            .filter({ hasText: /^Total$/ })
            .locator('xpath=following-sibling::dd[1]'),
    ).toHaveText('5.40');
    await page.getByRole('button', { name: 'Save order', exact: true }).click();
    await expect(page).toHaveURL(/\/orders\/\d+\/show$/);
    await expect(
        page.getByText('Browser Representative', { exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit order' })).toHaveCount(0);
});
