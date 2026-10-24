import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './support/auth';
import { seedStoreFixtures, storedProduct } from './support/storeFixtures';

test('image review retains notes after validation, replaces images, and keeps linked review history', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        App\Models\Product::factory()->imageUrl(null)->create(['name' => 'Browser image verification', 'ar_name' => '']);
    `);
    const id = storedProduct(
        'Browser image verification',
        '$product->id',
    ) as number;
    await loginAsAdmin(page);
    await page.goto(`/admin/products?product_id[]=${id}`);
    await page
        .getByRole('button', { name: 'Needs review', exact: true })
        .click();
    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByText('No reference image', { exact: true }),
    ).toBeVisible();
    await dialog
        .locator('#catalog-image-review-status')
        .selectOption('image_missing');
    await dialog
        .locator('#catalog-image-review-note')
        .fill('The packaging needs a reference photo');
    await page.route(`**/products/${id}/image-reviews*`, async (route) => {
        if (route.request().method() === 'POST') {
            await route.continue({
                postData: JSON.stringify({
                    ...route.request().postDataJSON(),
                    image_source: 'stale',
                }),
            });
        } else {
            await route.continue();
        }
    });
    await dialog
        .getByRole('button', { name: 'Save review', exact: true })
        .click();
    await expect(
        dialog.getByText(
            'The reference image changed. Reload the product and review it again.',
        ),
    ).toBeVisible();
    await expect(dialog.locator('#catalog-image-review-note')).toHaveValue(
        'The packaging needs a reference photo',
    );
    await page.unroute(`**/products/${id}/image-reviews*`);
    await dialog
        .getByRole('button', { name: 'Save review', exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Image missing', exact: true }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Image missing', exact: true })
        .click();
    await expect(
        dialog.getByText('The packaging needs a reference photo'),
    ).toBeVisible();
    await dialog
        .locator('#reference-image-upload')
        .setInputFiles('tests/e2e/fixtures/store-avatar.png');
    await dialog
        .getByRole('button', { name: 'Upload image', exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await page
        .getByRole('button', { name: 'Needs review', exact: true })
        .click();
    await expect(
        dialog.getByText('The packaging needs a reference photo'),
    ).toBeVisible();
    const currentImage = dialog.locator('img').first();
    await expect(currentImage).toBeVisible();
    await expect
        .poll(() =>
            currentImage.evaluate(
                (img) => (img as HTMLImageElement).naturalWidth,
            ),
        )
        .toBeGreaterThan(0);
    await dialog
        .locator('#catalog-image-review-status')
        .selectOption('matches');
    await dialog
        .getByRole('button', { name: 'Save review', exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await page.getByRole('button', { name: 'Matches', exact: true }).click();
    const reviewer = dialog.getByRole('link', { name: 'Test User' }).first();
    await expect(reviewer).toHaveAttribute('href', /\/admin\/users\/\d+$/);
    await reviewer.click();
    await expect(page).toHaveURL(/\/admin\/users\/\d+$/);
});
