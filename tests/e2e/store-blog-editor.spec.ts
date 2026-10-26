import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    arrowTo,
    colorsOf,
    documentViolations,
    expectStrictPolicy,
    focusedToolbarItem,
    php,
    press,
    uploadPhoto,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

/**
 * Blog post create/edit use the shared Tiptap editor, StoreRichTextField
 * (.ai/rules/store-backoffice-migration.md §18), under the strict Inertia CSP.
 */

/** Representative markup the retired Blade (summernote) editor stored. */
const legacyHtml =
    '<h2>Fresh <b>rice</b></h2><p style="text-align: center;"><font color="#ff0000">Red</font> and ' +
    '<span style="background-color: rgb(255, 255, 0);">marked</span></p><p style="margin-left: 25px;">Indented <u>under</u> <i>italic</i></p>' +
    '<table class="table table-bordered"><tbody><tr><td>A</td><td>B</td></tr></tbody></table>' +
    '<p><a href="https://example.com/rice" target="_blank">Link</a></p><p dir="rtl" lang="ar">أرز</p>' +
    '<div class="embed-responsive embed-responsive-16by9"><iframe src="//www.youtube.com/embed/abc"></iframe></div>';

function resetBlog(posts: Record<string, string> = {}): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('blogs')->delete();
        Illuminate\Support\Facades\DB::table('blog_categories')->delete();
        $category = new App\Models\BlogCategory;
        $category->forceFill(['category_name' => 'Browser recipes', 'slug' => 'browser-recipes'])->save();
        foreach ([${Object.entries(posts)
            .map(([slug, body]) => `${php(slug)} => ${php(body)}`)
            .join(', ')}] as $slug => $body) {
            App\Models\Blog::forceCreate(['category_id' => $category->id, 'title' => 'Seeded '.$slug, 'slug' => $slug, 'short_description' => 'Seeded summary', 'description' => $body, 'status' => 1]);
        }
    `);
}

/** Runs `$expression` against the stored post and returns its JSON. */
function stored(slug: string, expression: string): unknown {
    const file = join(tmpdir(), `store-blog-editor-${slug}.json`);
    seedStoreFixtures(String.raw`
        $blog = App\Models\Blog::query()->where('slug', ${php(slug)})->sole();
        file_put_contents(${php(file)}, json_encode(${expression}));
    `);

    return JSON.parse(readFileSync(file, 'utf8'));
}

test('a post is written, formatted by keyboard, saved and reloaded with a table and a canonical image under the strict CSP', async ({
    page,
}) => {
    resetBlog();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'blog-photo');
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/blog');
    const createVisit = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/blog/create' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page.getByRole('link', { name: 'Add post', exact: true }).click();
    expect((await createVisit).status()).toBe(200);
    await expect(
        page.getByRole('heading', { name: 'Add blog post', exact: true }),
    ).toBeVisible();

    await page.locator('#title').fill('Browser editor post');
    await expect(page.locator('#slug')).toHaveValue('browser-editor-post');
    await page
        .locator('#category_id')
        .selectOption({ label: 'Browser recipes' });
    await page.locator('#short_description').fill('A browser summary');

    // Keyboard only: the toolbar is one tab stop between the summary and
    // the content, and arrow keys move inside it.
    await page.locator('#short_description').focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#description')).toBeFocused();
    await page.keyboard.type('Keyboard heading');
    await page.keyboard.press('Shift+Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await expect(page.locator('#description h2')).toHaveText(
        'Keyboard heading',
    );
    await page.keyboard.press('Tab');
    await expect(page.locator('#description')).toBeFocused();
    await press(page, 'End', '#description');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Bold words');
    await press(page, 'Shift+Home', '#description', 'Bold words');
    await page.keyboard.press('Alt+F10');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('Enter');
    await expect(page.locator('#description')).toBeFocused();
    await expect(page.locator('#description strong')).toHaveText('Bold words');
    await expect(page.locator('[data-toolbar-item="bold"]')).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await page.keyboard.press('Alt+F10');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await arrowTo(page, 'center');
    await page.keyboard.press('Space');
    // Toolbar commands hand focus back to the content on the next frame.
    await expect(page.locator('#description')).toBeFocused();
    await expect(
        page.locator('#description p[style*="text-align: center"]'),
    ).toHaveText('Bold words');

    await press(page, 'End', '#description');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Alt+F10');
    await arrowTo(page, 'table');
    await page.keyboard.press('Enter');
    await expect(page.locator('#description table')).toBeVisible();
    await expect(page.locator('#description')).toBeFocused();
    await page.keyboard.type('Head');
    await page.keyboard.press('Tab');
    await page.keyboard.type('Cell');
    await expect(page.locator('#description th').first()).toHaveText('Head');

    await press(page, 'Control+End', '#description');
    await page.keyboard.type('Photo ');
    await page.locator('[data-toolbar-item="image"]').click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog
        .getByRole('button', { name: 'blog-photo', exact: true })
        .click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    const editorImage = page.locator('#description img[src]');
    await expect(editorImage).toHaveAttribute('src', /\/uploads\/all\//);
    await expect
        .poll(() =>
            editorImage.evaluate((img: HTMLImageElement) => img.naturalWidth),
        )
        .toBeGreaterThan(0);
    expect(await documentViolations(page)).toEqual([]);

    const created = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/blog' &&
            r.request().method() === 'POST',
    );
    await page
        .getByRole('button', { name: 'Create post', exact: true })
        .click();
    expect((await created).status()).toBe(302);
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    const body = stored('browser-editor-post', '$blog->description') as string;
    expect(body).toContain('<h2>Keyboard heading</h2>');
    expect(body).toMatch(
        /<p style="text-align: center"><strong>Bold words<\/strong><\/p>/,
    );
    expect(body).toMatch(/<th colspan="1" rowspan="1"><p>Head<\/p><\/th>/);
    expect(body).toMatch(
        /<p>Photo <img src="https:\/\/127\.0\.0\.1:8010\/uploads\/all\/[^"]+" alt="blog-photo"><\/p>/,
    );
    expect(body).not.toMatch(/data:|min-width/);

    const editVisit = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/blog\/\d+\/edit$/.test(
                new URL(r.url()).pathname,
            ) && r.request().headers()['x-inertia'] === 'true',
    );
    await page
        .getByRole('row', { name: /Browser editor post/ })
        .getByRole('link', { name: 'Edit', exact: true })
        .click();
    expect((await editVisit).status()).toBe(200);
    await expect(page).toHaveURL(/\/temp\/admin\/blog\/\d+\/edit$/);
    await expect(page.locator('#description h2')).toHaveText(
        'Keyboard heading',
    );
    await page.reload();
    await expect(
        page.getByRole('heading', { name: 'Edit blog post', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#title')).toHaveValue('Browser editor post');
    await expect(page.locator('#short_description')).toHaveValue(
        'A browser summary',
    );
    await expect(page.locator('#description h2')).toHaveText(
        'Keyboard heading',
    );
    await expect(
        page.locator('#description p[style*="text-align: center"] strong'),
    ).toHaveText('Bold words');
    await expect(page.locator('#description th').first()).toHaveText('Head');
    await expect
        .poll(() =>
            page
                .locator('#description img[src]')
                .evaluate((img: HTMLImageElement) => img.naturalWidth),
        )
        .toBeGreaterThan(0);
    await expect(page.locator('input[name="description"]')).toHaveValue(body);
    expect(await documentViolations(page)).toEqual([]);

    const updated = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/blog\/\d+$/.test(new URL(r.url()).pathname) &&
            r.request().method() === 'PUT',
    );
    await page.getByRole('button', { name: 'Save post', exact: true }).click();
    expect([302, 303]).toContain((await updated).status());
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    expect(stored('browser-editor-post', '$blog->description')).toBe(body);

    await page.goto('/temp/blog/browser-editor-post');
    const article = page.locator('div.mb-4.overflow-hidden');
    await expect(article.locator('h2')).toHaveText('Keyboard heading');
    await expect(
        article.locator('p[style*="text-align: center"] strong'),
    ).toHaveText('Bold words');
    await expect(article.locator('th').first()).toHaveText('Head');
    await expect
        .poll(() =>
            article
                .locator('img')
                .evaluate((img: HTMLImageElement) => img.naturalWidth),
        )
        .toBeGreaterThan(0);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('script payloads stay inert in the editor and on the public page, stored or pasted', async ({
    page,
}) => {
    resetBlog({
        'hostile-post':
            '<p>Safe text</p><img src="x" onerror="alert(\'stored\')"><script>alert(\'stored\')</script>' +
            '<a href="javascript:alert(\'stored\')">Bad link</a>',
    });
    const dialogs: string[] = [];
    page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message());
        await dialog.dismiss();
    });
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);

    await page.goto('/temp/blog/hostile-post');
    await expect(page.getByText('Safe text')).toBeVisible();
    const publicHtml = await page
        .locator('div.mb-4.overflow-hidden')
        .innerHTML();
    expect(publicHtml).not.toMatch(/onerror|<script|javascript:/);

    await page.goto('/temp/admin/blog');
    await page
        .getByRole('row', { name: /Seeded hostile-post/ })
        .getByRole('link', { name: 'Edit', exact: true })
        .click();
    await expect(page.locator('#description')).toContainText('Safe text');
    expect(await page.locator('#description').innerHTML()).not.toMatch(
        /onerror|<script|javascript:/,
    );

    await page.locator('#description p').first().click();
    await press(page, 'End', '#description');
    await page.evaluate(() => {
        const data = new DataTransfer();
        data.setData(
            'text/html',
            '<p>Pasted <img src="x" onerror="alert(\'pasted\')"><img src="data:image/png;base64,iVBORw0KGgo="></p><script>alert(\'pasted\')</script>',
        );
        data.setData('text/plain', 'Pasted');
        document.querySelector('#description')!.dispatchEvent(
            new ClipboardEvent('paste', {
                clipboardData: data,
                bubbles: true,
                cancelable: true,
            }),
        );
    });
    await expect(page.locator('#description')).toContainText('Pasted');
    expect(await page.locator('#description').innerHTML()).not.toMatch(
        /onerror|<script|data:/,
    );
    const saved = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/blog\/\d+$/.test(new URL(r.url()).pathname) &&
            r.request().method() === 'PUT',
    );
    await page.getByRole('button', { name: 'Save post', exact: true }).click();
    expect([302, 303]).toContain((await saved).status());
    const body = stored('hostile-post', '$blog->description') as string;
    expect(body).toContain('Pasted');
    expect(body).not.toMatch(/onerror|<script|javascript:|data:|alert/);

    await page.goto('/temp/blog/hostile-post');
    await expect(page.getByText('Pasted')).toBeVisible();
    expect(dialogs).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('legacy Blade-editor markup round-trips unchanged and keeps its formatting when edited', async ({
    page,
}) => {
    resetBlog({ 'legacy-post': legacyHtml });
    const cleaned = stored('legacy-post', 'clean_html($blog->description)');
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    const publicBody = async () => {
        await page.goto('/temp/blog/legacy-post');

        return page.locator('div.mb-4.overflow-hidden').innerHTML();
    };
    const before = await publicBody();

    await page.goto('/temp/admin/blog');
    await page
        .getByRole('row', { name: /Seeded legacy-post/ })
        .getByRole('link', { name: 'Edit', exact: true })
        .click();
    await expect(page.locator('#description table')).toHaveClass(
        'table table-bordered',
    );
    await expect(page.locator('input[name="description"]')).toHaveValue(
        cleaned as string,
    );
    await page.getByRole('button', { name: 'Save post', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    expect(stored('legacy-post', '$blog->description')).toBe(cleaned);
    expect(await publicBody()).toBe(before);

    await page.goto('/temp/admin/blog');
    await page
        .getByRole('row', { name: /Seeded legacy-post/ })
        .getByRole('link', { name: 'Edit', exact: true })
        .click();
    await page.locator('#description p[dir="rtl"]').click();
    await press(page, 'End', '#description');
    await page.keyboard.type(' edited');
    await page.getByRole('button', { name: 'Save post', exact: true }).click();
    await expect(page).toHaveURL(/\/temp\/admin\/blog$/);
    const edited = stored('legacy-post', '$blog->description') as string;

    for (const kept of [
        '<h2>Fresh <strong>rice</strong></h2>',
        '<p style="text-align: center">Red and <span style="background-color: rgb(255, 255, 0)">marked</span></p>',
        '<p style="margin-left: 25px">Indented <u>under</u> <em>italic</em></p>',
        '<table class="table table-bordered">',
        'href="https://example.com/rice"',
        '<p dir="rtl" lang="ar">أرز edited</p>',
    ]) {
        expect(edited).toContain(kept);
    }

    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('the editor works in Arabic RTL with dark contrast and mirrored arrow keys', async ({
    page,
}) => {
    resetBlog({ 'rtl-post': '<p>نص <strong>عريض</strong></p>' });
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/blog');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await page
        .getByRole('row', { name: /Seeded rtl-post/ })
        .getByRole('link', { name: 'تعديل', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'تعديل مقال المدونة', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('toolbar', { name: 'التنسيق' })).toBeVisible();
    await expect(page.locator('#description-label')).toHaveText('المحتوى');
    await expect(page.locator('#description strong')).toHaveText('عريض');

    await page.locator('#short_description').focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowLeft');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await expect(page.locator('[data-toolbar-item="bold"]')).toHaveAttribute(
        'aria-label',
        'عريض',
    );
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');

    for (const selector of ['#description', 'select[data-toolbar-item]']) {
        const colors = await colorsOf(page, selector);
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    expect(await documentViolations(page)).toEqual([]);
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});
