import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { openAccountMenu } from './support/nav';

test('CMS contenteditable formatting and HTML source survive a refused write', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/website/custom-pages/create');
    await page.locator('#title').fill('Editor browser check');
    await page.locator('#slug').fill('terms-conditions');
    const editor = page.locator('#content.ProseMirror');
    await editor.fill('Formatted content');
    await editor.press('ControlOrMeta+a');
    await page.getByRole('button', { name: 'Bold', exact: true }).click();
    await expect(editor.locator('strong')).toHaveText('Formatted content');
    await page
        .getByRole('button', { name: 'HTML source', exact: true })
        .click();
    await expect(page.locator('#content-source')).toHaveValue(
        /<strong>Formatted content<\/strong>/,
    );
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click();
    await expect(page.getByText(/slug.*already.*taken/i)).toBeVisible();
    await expect(page.locator('#content-source')).toHaveValue(
        /Formatted content/,
    );
});

test('editor inherits RTL and keeps dark-mode text readable', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/website/custom-pages/create');
    await page.emulateMedia({ colorScheme: 'dark' });
    await openAccountMenu(page);
    await page.getByRole('button', { name: 'Arabic', exact: true }).click();
    const editor = page.locator('#content.ProseMirror');
    await expect(editor).toHaveCSS('direction', 'rtl');
    await expect(
        page.getByRole('button', { name: 'عريض', exact: true }),
    ).toBeVisible();
    const colours = await editor.evaluate((el) => {
        const style = getComputedStyle(el);
        const rgb = (value: string) => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d')!;
            context.fillStyle = value;
            context.fillRect(0, 0, 1, 1);

            return Array.from(context.getImageData(0, 0, 1, 1).data).slice(
                0,
                3,
            ) as [number, number, number];
        };

        return {
            text: rgb(style.color),
            background: rgb(style.backgroundColor),
        };
    });
    expect(
        contrastRatio(colours.text, colours.background),
    ).toBeGreaterThanOrEqual(4.5);
});
