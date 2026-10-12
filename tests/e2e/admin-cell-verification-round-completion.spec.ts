import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('an admin finishes a workers round and sees its persisted status while retaining report filters', async ({
    page,
}) => {
    await loginAsAdmin(page);
    await page.goto('/admin/cell-verification-rounds?completed=0');

    const roundLink = page
        .locator('tbody tr')
        .first()
        .locator('td')
        .first()
        .getByRole('link');
    const roundHref = await roundLink.getAttribute('href');
    expect(roundHref).toMatch(/^\/admin\/cell-verification-rounds\/\d+$/);
    const roundURL = `${roundHref}?is_correct=0&per_page=10`;
    await page.goto(roundURL);

    const finishButton = page.getByRole('button', {
        name: 'Finish round',
        exact: true,
    });
    const completionStatus = page
        .getByText('Completed', { exact: true })
        .locator('..')
        .locator('span');
    await expect(finishButton).toBeVisible();
    await expect(completionStatus).toHaveText('In progress');
    await finishButton.click();

    await expect(finishButton).toHaveCount(0);
    await expect(page).toHaveURL(roundURL);
    await expect(completionStatus).toHaveText(/\d/);

    await page.reload();
    await expect(finishButton).toHaveCount(0);
    await expect(page).toHaveURL(roundURL);
    await expect(completionStatus).toHaveText(/\d/);
});
