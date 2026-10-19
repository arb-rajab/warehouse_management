import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

/**
 * The legacy uploader on a page that keeps it long-term: the seller panel's
 * product form (`seller.products.create`), which stays Blade with the seller
 * area (.ai/rules/store-backoffice-migration.md §4.1). The admin product
 * create form it used to run on is Inertia since §22. The seller is seeded in
 * the e2e database by tests/e2e/support/seed-store-orders.php.
 */
async function loginAsSeller(page: Page): Promise<void> {
    await page.goto('/temp/login');
    // The storefront's header carries a second, hidden login form.
    const form = page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: 'Login' }) });
    await form.locator('#email').fill('browser-seller@example.com');
    await form.locator('#password').fill('password');
    await form.getByRole('button', { name: 'Login' }).click();
    await expect(page).not.toHaveURL(/\/temp\/login/);
}

test('retained Blade uploader uploads, selects, removes and deletes an attachment over the temp URLs', async ({
    page,
}) => {
    await loginAsSeller(page);
    const response = await page.goto('/temp/seller/product/create');
    expect(response?.status()).toBe(200);
    expect(response?.headers()['content-security-policy']).toContain(
        "'unsafe-inline'",
    );
    await page
        .locator('[data-toggle="aizuploader"]', {
            has: page.locator('input[name=thumbnail_img]'),
        })
        .click();
    const modal = page.locator('#aizUploaderModal');
    await expect(modal).toBeVisible();
    await modal.getByRole('link', { name: 'Upload New' }).click();
    const upload = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/aiz-uploader/upload' &&
            r.request().method() === 'POST',
    );
    await modal.locator('input[type=file]').setInputFiles({
        name: 'legacy-audit.png',
        mimeType: 'image/png',
        buffer: readFileSync('tests/e2e/fixtures/store-avatar.png'),
    });
    const uploaded = await upload;
    expect(uploaded.status()).toBe(200);
    expect(new URL(uploaded.url()).origin).toBe(new URL(page.url()).origin);
    await modal.getByRole('link', { name: 'Select File', exact: true }).click();
    const thumbnail = modal.locator(
        '.aiz-uploader-select[title="legacy-audit.png"] img',
    );
    await expect
        .poll(() =>
            thumbnail.evaluate((img: HTMLImageElement) => img.naturalWidth),
        )
        .toBeGreaterThan(0);
    expect(new URL((await thumbnail.getAttribute('src'))!).origin).toBe(
        new URL(page.url()).origin,
    );
    await modal
        .locator('.aiz-uploader-select[title="legacy-audit.png"]')
        .click();
    await modal.getByRole('button', { name: 'Add Files', exact: true }).click();
    const field = page.locator('input[name=thumbnail_img]');
    await expect(field).not.toHaveValue('');
    const selectedId = await field.inputValue();
    const previews = field
        .locator('..')
        .locator('..')
        .locator('.file-preview-item');
    await expect(previews).toHaveCount(1);
    await field
        .locator('..')
        .locator('..')
        .locator('.remove-attachment')
        .click();
    await expect(field).toHaveValue('');
    await expect(previews).toHaveCount(0);
    const listed = await page.request.get(
        '/temp/aiz-uploader/get_uploaded_files',
    );
    expect(
        (await listed.json()).data.map((file: { id: number }) =>
            String(file.id),
        ),
    ).toContain(selectedId);
    const csrf = await page
        .locator('meta[name="csrf-token"]')
        .getAttribute('content');
    const removed = await page.request.post(
        `/temp/seller/uploads/destroy/${selectedId}`,
        {
            headers: { 'X-CSRF-TOKEN': csrf!, Referer: page.url() },
            maxRedirects: 0,
        },
    );
    expect(removed.status()).toBe(302);
    const remaining = await page.request.get(
        '/temp/aiz-uploader/get_uploaded_files',
    );
    expect(remaining.ok()).toBe(true);
    expect(
        (await remaining.json()).data.map((file: { id: number }) =>
            String(file.id),
        ),
    ).not.toContain(selectedId);
});

test('the retained Blade login hands over to Inertia in a full load', async ({
    page,
}) => {
    const response = await page.goto('/temp/login');
    expect(response?.headers()['content-security-policy']).toContain(
        "'unsafe-inline'",
    );
    const inertiaLoad = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin' &&
            r.request().isNavigationRequest(),
    );
    await loginAsStoreAdmin(page);
    expect((await inertiaLoad).status()).toBe(200);
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Dashboard', exact: true }),
    ).toBeVisible();
});
