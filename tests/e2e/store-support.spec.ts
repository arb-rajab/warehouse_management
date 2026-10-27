import { expect, test } from '@playwright/test';
import {
    paginated,
    storeConversation,
    storeProductQuery,
} from '../../resources/js/testing/factories';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';

/** Browser-only behavior over a fixture response; Pest covers the real records and writes. */
test('conversation deletes use native confirmation and pagination arrows follow RTL', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    let deleted = false;
    let writes = 0;
    await page.route('**/temp/admin/conversations/41', async (route) => {
        expect(route.request().method()).toBe('DELETE');
        writes++;
        deleted = true;
        await route.fulfill({
            status: 303,
            headers: { location: '/temp/admin/conversations' },
        });
    });
    await page.route('**/temp/admin/conversations', async (route) => {
        const response = await route.fetch();
        const update = (data: { props: Record<string, unknown> }) => {
            const conversations = paginated(
                deleted ? [] : [storeConversation()],
            );
            conversations.meta.links = [
                { url: null, label: 'Previous', active: false },
                { url: '/temp/admin/conversations', label: '1', active: true },
                {
                    url: '/temp/admin/conversations?page=2',
                    label: '2',
                    active: false,
                },
                {
                    url: '/temp/admin/conversations?page=2',
                    label: 'Next',
                    active: false,
                },
            ];
            data.props.conversations = conversations;
            data.props.enabled = true;

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
    await page.goto('/temp/admin/conversations');
    const row = page.getByRole('row', { name: /Delivery question/ });
    const remove = row.getByRole('button', { name: 'Delete' });
    page.once('dialog', (dialog) => dialog.dismiss());
    await remove.click();
    await expect(row).toBeVisible();
    expect(writes).toBe(0);

    const arrow = page.locator('main nav svg').first();
    await expect(arrow).toHaveCSS('rotate', 'none');
    await page.evaluate(() =>
        document.documentElement.setAttribute('dir', 'rtl'),
    );
    await expect(arrow).toHaveCSS('rotate', '180deg');
    await page.evaluate(() =>
        document.documentElement.setAttribute('dir', 'ltr'),
    );

    page.once('dialog', (dialog) => dialog.accept());
    await remove.click();
    await expect(row).toHaveCount(0);
    expect(writes).toBe(1);
});

test('a failed reply retains text and the native field has dark-mode styling', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    let refused = false;
    await page.route('**/temp/admin/product-queries/43', async (route) => {
        if (route.request().method() === 'PUT') {
            refused = true;
        }

        const response = await route.fetch({
            url: new URL('/temp/admin/product-queries', route.request().url())
                .href,
            method: 'GET',
        });
        const update = (data: {
            component: string;
            url: string;
            props: Record<string, unknown>;
        }) => {
            data.component = 'Store/Admin/Support/ProductQueries/Show';
            data.url = '/temp/admin/product-queries/43';
            data.props.query = storeProductQuery();
            data.props.canReply = true;
            data.props.errors = refused ? { reply: 'Reply was refused.' } : {};

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
    await page.goto('/temp/admin/product-queries/43');
    const field = page.getByRole('textbox', {
        name: 'Type your reply required',
        exact: true,
    });
    await expect(field).toHaveAttribute('required', '');
    await field.fill('Keep this reply');
    const colors = await field.evaluate((element) => {
        const style = getComputedStyle(element);
        const toRgb = (color: string) => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d') as CanvasRenderingContext2D;
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return Array.from(
                context.getImageData(0, 0, 1, 1).data.slice(0, 3),
            );
        };

        return {
            text: toRgb(style.color),
            background: toRgb(style.backgroundColor),
        };
    });
    expect(
        contrastRatio(colors.text, colors.background),
    ).toBeGreaterThanOrEqual(4.5);
    await page.getByRole('button', { name: 'Send', exact: true }).click();
    await expect(
        page.getByText('Reply was refused.', { exact: true }),
    ).toBeVisible();
    await expect(field).toHaveValue('Keep this reply');
});
