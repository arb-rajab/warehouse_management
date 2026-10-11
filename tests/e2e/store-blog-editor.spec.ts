import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

test('blog source editing accepts safe HTML and retains rich text and metadata after a server error', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/blog-category/create');
    await page.locator('#category_name').fill('Blog editor recipes');
    await page.getByRole('button', { name: 'Create category' }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog-category$/);
    const slug = 'blog-editor-retention';
    const fillPost = async () => {
        await page.locator('#title').fill('وصفة الأرز');
        await page.locator('#slug').fill(slug);
        await page
            .locator('#category_id')
            .selectOption({ label: 'Blog editor recipes' });
        await page.locator('#short_description').fill('وصفة طازجة');
        await page.locator('#meta_title').fill('Recipe metadata');
        await page.locator('#meta_description').fill('Retained metadata');
        await page
            .getByRole('button', { name: 'Edit HTML', exact: true })
            .click();
        await page
            .locator('#description-source')
            .fill(
                '<h2 dir="rtl">وصفة محفوظة</h2><p>Safe <strong>rice</strong></p><table><tbody><tr><td>Rice</td></tr></tbody></table><script>window.blogEditorUnsafe=true</script><a href="javascript:alert(1)">Link</a>',
            );
        await expect(page.locator('input[name="description"]')).not.toHaveValue(
            /script|javascript:/,
        );
        await page
            .getByRole('button', { name: 'Visual editor', exact: true })
            .click();
        await expect(page.locator('#description-editor h2')).toHaveText(
            'وصفة محفوظة',
        );
        await expect(page.locator('#description-editor table')).toBeVisible();
        expect(
            await page.evaluate(
                () =>
                    (window as Window & { blogEditorUnsafe?: boolean })
                        .blogEditorUnsafe,
            ),
        ).toBeUndefined();
    };
    await page.goto('/temp/admin/blog/create');
    await fillPost();
    await page
        .getByRole('button', { name: 'Create post', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    await page.goto('/temp/admin/blog/create');
    await fillPost();
    await page
        .getByRole('button', { name: 'Create post', exact: true })
        .click();
    await expect(
        page.getByText('The slug has already been taken.'),
    ).toBeVisible();
    await expect(page.locator('#title')).toHaveValue('وصفة الأرز');
    await expect(page.locator('#slug')).toHaveValue(slug);
    await expect(page.locator('#short_description')).toHaveValue('وصفة طازجة');
    await expect(page.locator('#meta_title')).toHaveValue('Recipe metadata');
    await expect(page.locator('#meta_description')).toHaveValue(
        'Retained metadata',
    );
    await expect(page.locator('#description-editor')).toContainText(
        'وصفة محفوظة',
    );
    await expect(page.locator('input[name="description"]')).toHaveValue(
        /<strong>rice<\/strong>/,
    );
    await page.locator('#slug').fill('blog-editor-after-validation');
    await page
        .getByRole('button', { name: 'Create post', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    const publicResponse = await page.goto(
        '/temp/blog/blog-editor-after-validation',
    );
    expect(publicResponse?.status()).toBe(200);
    expect(publicResponse?.headers()['x-inertia']).toBeUndefined();
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
        'content',
        'Recipe metadata',
    );
    await expect(page.locator('meta[property="og:url"]')).toHaveAttribute(
        'content',
        /\/temp\/blog\/blog-editor-after-validation$/,
    );
    await expect(page.getByText('وصفة محفوظة', { exact: true })).toBeVisible();
    await expect(
        page.locator('script').filter({ hasText: 'blogEditorUnsafe' }),
    ).toHaveCount(0);
    await page.goto('/temp/admin/blog');
    const savedRow = page.getByRole('row').filter({
        has: page.locator('a[href$="/temp/blog/blog-editor-after-validation"]'),
    });
    await savedRow
        .getByRole('button', { name: 'Unpublish', exact: true })
        .click();
    await expect(
        savedRow.getByRole('button', { name: 'Publish', exact: true }),
    ).toBeVisible();
    await savedRow
        .getByRole('button', { name: 'Publish', exact: true })
        .click();
    await expect(
        savedRow.getByRole('button', { name: 'Unpublish', exact: true }),
    ).toBeVisible();
    page.once('dialog', (dialog) => dialog.dismiss());
    await savedRow.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(savedRow).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await savedRow.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(savedRow).toHaveCount(0);
});
