import { expect, test } from '@playwright/test';
import {
    paginated,
    storeWebsitePage,
} from '../../resources/js/testing/factories';
import { loginAsStoreAdmin } from './support/auth';

/** Pest verifies real deletion; the browser drives confirmation and Inertia form navigation. */
test('website pages confirm deletes and open the migrated page form through Inertia', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    let writes = 0;
    let deleted = false;
    await page.route('**/temp/admin/website/custom-pages/44', async (route) => {
        expect(route.request().method()).toBe('DELETE');
        writes++;
        deleted = true;
        await route.fulfill({
            status: 303,
            headers: { location: '/temp/admin/website/pages' },
        });
    });
    await page.route('**/temp/admin/website/pages', async (route) => {
        const response = await route.fetch();
        const update = (data: { props: Record<string, unknown> }) => {
            data.props.pages = paginated(deleted ? [] : [storeWebsitePage()]);

            return data;
        };

        if (route.request().headers()['x-inertia']) {
            await route.fulfill({
                response,
                json: update(await response.json()),
            });
        } else {
            const body = (await response.text()).replace(
                /(<script data-page="app" type="application\/json">)([\s\S]*?)(<\/script>)/,
                (_, start, json, end) =>
                    start + JSON.stringify(update(JSON.parse(json))) + end,
            );
            await route.fulfill({ response, body });
        }
    });
    await page.goto('/temp/admin/website/pages');
    const row = page.getByRole('row', { name: /About us/ });
    page.once('dialog', (dialog) => dialog.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    expect(writes).toBe(0);
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    expect(writes).toBe(1);

    const createRequest = page.waitForRequest((request) =>
        request.url().endsWith('/temp/admin/website/custom-pages/create'),
    );
    await page.getByRole('link', { name: 'Add new page', exact: true }).click();
    expect((await createRequest).headers()['x-inertia']).toBe('true');
    await expect(page).toHaveURL(
        /\/temp\/admin\/website\/custom-pages\/create$/,
    );
    await expect(page.locator('#title')).toBeVisible();
});
