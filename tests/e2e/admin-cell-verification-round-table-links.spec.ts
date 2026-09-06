import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';

test('the cell verification rounds listing and round page link the worker to their admin show page', async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/cell-verification-rounds');

    const firstRow = page.locator('tbody tr').first();
    const listingWorkerLink = firstRow
        .locator('td')
        .nth(1)
        .getByRole('link')
        .first();
    const workerHref = await listingWorkerLink.getAttribute('href');
    expect(workerHref).toMatch(/^\/admin\/users\/\d+$/);
    const workerName = (await listingWorkerLink.innerText()).trim();

    const roundLink = firstRow.locator('td').nth(0).getByRole('link').first();
    await roundLink.click();
    await expect(page).toHaveURL(/\/admin\/cell-verification-rounds\/\d+$/);

    const showWorkerLink = page.getByRole('link', { name: workerName });
    await expect(showWorkerLink).toHaveAttribute('href', workerHref!);

    await showWorkerLink.click();
    await expect(page).toHaveURL(/\/admin\/users\/\d+$/);
    await expect(page.locator('h1')).toContainText(workerName);
});

test("a user's admin page links their verification reports to the round and cell they belong to", async ({
    page,
}) => {
    await loginAsAdmin(page);

    await page.goto('/admin/cell-verification-rounds');

    const firstRow = page.locator('tbody tr').first();
    const workerHref = await firstRow
        .locator('td')
        .nth(1)
        .getByRole('link')
        .first()
        .getAttribute('href');
    const userId = workerHref!.match(/\/admin\/users\/(\d+)$/)![1];

    await page.goto(`/admin/users/${userId}`);

    const reportsTable = page.locator('table').nth(1);
    const reportRow = reportsTable.locator('tbody tr').first();

    const roundLink = reportRow.locator('td').nth(0).getByRole('link').first();
    await expect(roundLink).toHaveAttribute(
        'href',
        /^\/admin\/cell-verification-rounds\/\d+$/,
    );

    const cellLink = reportRow.locator('td').nth(1).getByRole('link').first();
    await expect(cellLink).toHaveAttribute(
        'href',
        /^\/admin\/rows\/[A-Za-z0-9]+$/,
    );

    await roundLink.click();
    await expect(page).toHaveURL(/\/admin\/cell-verification-rounds\/\d+$/);
});
