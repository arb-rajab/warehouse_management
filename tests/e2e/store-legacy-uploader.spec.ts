import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('retained Blade uploader uploads, selects and removes an attachment over the temp URLs', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    const response = await page.goto('/temp/admin/carriers/create');
    expect(response?.headers()['content-security-policy']).toContain(
        "'unsafe-inline'",
    );
    await page.locator('[data-toggle="aizuploader"]').click();
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
    await expect(page.locator('input[name=logo]')).not.toHaveValue('');
    const selectedId = await page.locator('input[name=logo]').inputValue();
    await expect(page.locator('.file-preview-item')).toHaveCount(1);
    await page.locator('.remove-attachment').click();
    await expect(page.locator('input[name=logo]')).toHaveValue('');
    await expect(page.locator('.file-preview-item')).toHaveCount(0);
    const csrf = await page
        .locator('meta[name="csrf-token"]')
        .getAttribute('content');
    const removed = await page.request.delete(
        `/temp/admin/uploads/${selectedId}`,
        {
            headers: { 'X-CSRF-TOKEN': csrf!, Referer: page.url() },
            maxRedirects: 0,
        },
    );
    expect(removed.status()).toBe(200);
    expect(await removed.json()).toEqual({ deleted: Number(selectedId) });
    const remaining = await page.request.get(
        '/temp/aiz-uploader/get_uploaded_files',
    );
    expect(remaining.ok()).toBe(true);
    expect(
        (await remaining.json()).data.map((file: { id: number }) =>
            String(file.id),
        ),
    ).not.toContain(selectedId);
    const inertiaLoad = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/staffs' &&
            r.request().isNavigationRequest(),
    );
    await page
        .getByRole('link', { name: /Staffs/, exact: false })
        .filter({ hasText: 'Staffs' })
        .first()
        .click();
    await page.locator('a[href$="/temp/admin/staffs"]').click();
    expect((await inertiaLoad).status()).toBe(200);
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Staff', exact: true }),
    ).toBeVisible();
});
