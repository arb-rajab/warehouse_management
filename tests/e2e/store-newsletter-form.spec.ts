import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('newsletter rejects a forged recipient and retains subject and rich text after server validation', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/newsletter');
    await page.locator('#subject').fill('Newsletter retention subject');
    const editor = page.locator('#content-editor');
    await editor.fill('Newsletter retention content');
    const recipients = page.locator('#user_emails');
    await recipients.evaluate(async (element) => {
        const option = new Option(
            'missing-marketing-recipient@example.test',
            'missing-marketing-recipient@example.test',
            true,
            true,
        );
        element.appendChild(option);
        element.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise((resolve) => requestAnimationFrame(resolve));
        option.remove();
    });
    await recipients.selectOption('missing-marketing-recipient@example.test');
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(
        page.getByText('The selected user_emails.0 is invalid.'),
    ).toBeVisible();
    await expect(page.locator('#subject')).toHaveValue(
        'Newsletter retention subject',
    );
    await expect(editor).toHaveText('Newsletter retention content');
    await expect(recipients).toHaveValues([
        'missing-marketing-recipient@example.test',
    ]);
});
