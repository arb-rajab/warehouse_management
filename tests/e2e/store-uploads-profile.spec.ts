import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import { ensureStoreRtlLanguage } from './support/storeFixtures';

test('upload rejection, avatar selection, validation retention and native deletion confirmation', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    await page
        .locator('#upload-files')
        .setInputFiles('tests/e2e/fixtures/store-refused.png');
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(page.getByRole('alert')).toContainText('unsupported');
    await page
        .locator('#upload-files')
        .setInputFiles('tests/e2e/fixtures/store-avatar.png');
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    await expect(
        page.getByRole('button', { name: 'store-avatar', exact: true }),
    ).toBeVisible();
    await page.goto('/temp/admin/profile');
    await page.getByRole('button', { name: 'Choose files' }).click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'store-avatar', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    await expect(page.locator('input[name="avatar"]')).not.toHaveValue('');
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(page.getByRole('img', { name: 'store-avatar' })).toBeVisible();
    // This email exists in the WMS seed, not store accounts: use a duplicate store
    // staff account to provoke authoritative uniqueness validation.
    await page.goto('/temp/admin/staffs/create');
    await page.locator('#name').fill('Profile email owner');
    await page.locator('#email').fill('profile-owner@example.com');
    await page.locator('#mobile').fill('0612345678');
    await page.locator('#role_id').selectOption({ label: 'Clerk' });
    await page.locator('#password').fill('Password123!');
    await page.locator('#password_confirmation').fill('Password123!');
    await page.getByRole('button', { name: 'Create staff member' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/staffs$/);
    await page.goto('/temp/admin/profile');
    await page.locator('#name').fill('Retained profile name');
    await page.locator('#email').fill('profile-owner@example.com');
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(
        page.getByText('The email has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#name')).toHaveValue('Retained profile name');
    await expect(page.locator('#email')).toHaveValue(
        'profile-owner@example.com',
    );
    await page.goto('/temp/admin/uploads');
    const file = page
        .getByRole('button', { name: 'store-avatar', exact: true })
        .locator('..');
    page.once('dialog', (dialog) => dialog.dismiss());
    await file.getByRole('button', { name: 'Delete' }).click();
    await expect(file).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await file.getByRole('button', { name: 'Delete' }).click();
    await expect(
        page.getByRole('button', { name: 'store-avatar', exact: true }),
    ).toHaveCount(0);
});

test('the picker supports RTL keyboard focus and dark inputs', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/profile');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await page.getByRole('button', { name: 'اختيار الملفات' }).click();
    const dialog = page.getByRole('dialog', { name: 'اختيار الملفات' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'اختيار الملفات' }),
    ).toBeFocused();
    const colors = await page.locator('#email').evaluate((input) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d')!;
        const rgb = (color: string) => {
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
        };
        const style = getComputedStyle(input);

        return {
            foreground: rgb(style.color),
            background: rgb(style.backgroundColor),
        };
    });
    expect(Math.max(...colors.background)).toBeLessThan(60);
    expect(
        contrastRatio(colors.foreground, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
});
