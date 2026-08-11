import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the add row form keeps what was typed after a duplicate-letter validation error', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZZ');
    await page.locator('#cells_count').fill('5');
    await page.locator('#flats_count').fill('3');
    await page.getByRole('button', { name: 'Create row' }).click();
    await expect(page).toHaveURL(/\/admin\/rows\/ZZ$/);

    await page.goto('/admin/rows/create');
    await page.locator('#letter').fill('ZZ');
    await page.locator('#cells_count').fill('5');
    await page.locator('#flats_count').fill('3');
    await page.getByRole('button', { name: 'Create row' }).click();

    await expect(
        page.getByText('The letter has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#letter')).toHaveValue('ZZ');
    await expect(page.locator('#cells_count')).toHaveValue('5');
    await expect(page.locator('#flats_count')).toHaveValue('3');
});

test('the add user form keeps what was typed after a duplicate-email validation error', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/users/create');
    await page.locator('#name').fill('Jane Doe');
    await page.locator('#email').fill('test@example.com');
    await page.locator('#password').fill('Password123!');
    await page.locator('#password_confirmation').fill('Password123!');
    await page.getByRole('button', { name: 'Create user' }).click();

    await expect(
        page.getByText('The email has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('Jane Doe');
    await expect(page.locator('#email')).toHaveValue('test@example.com');
});
