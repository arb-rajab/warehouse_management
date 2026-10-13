import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
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
 * The website footer, appearance and custom pages on Inertia with the shared
 * editor and their typed writers; the header and footer scripts keep their
 * compatibility endpoint (.ai/rules/store-backoffice-migration.md §20).
 */

const FOOTER_KEYS = [
    'footer_logo',
    'about_us_description',
    'play_store_link',
    'app_store_link',
    'contact_address',
    'contact_phone',
    'contact_email',
    'widget_one',
    'widget_one_labels',
    'widget_one_links',
    'frontend_copyright_text',
    'show_social_links',
    'facebook_link',
    'twitter_link',
    'instagram_link',
    'youtube_link',
    'linkedin_link',
    'payment_method_images',
    'registration_tutorial_button',
    'registration_tutorial_text',
    'registration_tutorial_link',
];
const APPEARANCE_KEYS = [
    'website_name',
    'site_motto',
    'site_icon',
    'base_color',
    'base_hov_color',
    'login_page_image',
    'register_page_image',
    'flash_deal_banner',
    'flash_deal_banner_small',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'meta_image',
    'cookies_agreement_text',
    'show_cookies_agreement',
    'show_website_popup',
    'website_popup_content',
    'show_subscribe_form',
    'header_script',
    'footer_script',
];

/** Clears both forms' settings and switches the website setup menu on. */
function resetWebsite(settings: Record<string, string> = {}): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->whereIn('type', json_decode(${php(JSON.stringify([...FOOTER_KEYS, ...APPEARANCE_KEYS]))}, true))->delete();
        foreach (['website_setup', 'website_setup_footer', 'website_setup_pages', 'website_setup_appearance'] as $key) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $key, 'lang' => null], ['value' => '1']);
        }
        foreach (json_decode(${php(JSON.stringify(settings))}, true) as $key => $value) {
            [$type, $lang] = array_pad(explode('@', $key), 2, null);
            Illuminate\Support\Facades\DB::table('business_settings')->insert(['type' => $type, 'lang' => $lang, 'value' => $value]);
        }
        foreach (App\Models\Page::query()->where('slug', 'like', 'Browser-%')->orWhere('slug', 'like', 'browser-%')->pluck('id') as $id) {
            App\Models\PageTranslation::query()->where('page_id', $id)->delete();
            App\Models\Page::query()->whereKey($id)->delete();
        }
    `);
}

function seedPage(slug: string, content: string): void {
    seedStoreFixtures(String.raw`
        $page = App\Models\Page::forceCreate(['type' => 'custom_page', 'title' => 'Seeded '.${php(slug)}, 'slug' => ${php(slug)}, 'content' => ${php(content)}]);
        App\Models\PageTranslation::create(['page_id' => $page->id, 'lang' => 'en', 'title' => 'Seeded '.${php(slug)}, 'content' => ${php(content)}]);
    `);
}

/** Runs a PHP expression against the browser database and returns its JSON. */
function stored(name: string, expression: string): unknown {
    const file = join(tmpdir(), `store-website-settings-${name}.json`);
    seedStoreFixtures(String.raw`
        file_put_contents(${php(file)}, json_encode(${expression}));
    `);

    return JSON.parse(readFileSync(file, 'utf8'));
}

async function choosePhoto(page: Page, field: string, name: string) {
    await page
        .locator(`#${field}`)
        .locator('..')
        .getByRole('button', { name: 'Choose files', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Choose files' });
    await dialog.getByRole('button', { name, exact: true }).click();
    await dialog.getByRole('button', { name: 'Use selected files' }).click();
    await expect(dialog).toHaveCount(0);
}

/** Replaces the editor's content with `text`, then makes it bold by keyboard. */
async function typeBold(page: Page, editor: string, text: string) {
    await page.locator(editor).click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type(text);
    await press(page, 'Shift+Home', editor);
    await page.keyboard.press('Alt+F10');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('Enter');
    await expect(page.locator(editor)).toBeFocused();
    await expect(page.locator(`${editor} strong`)).toHaveText(text);
}

test('the footer saves and reloads a canonical logo, editor content and link rows, and the storefront renders them', async ({
    page,
}) => {
    resetWebsite();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'footer-photo');
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/website/footer?lang=en');
    await expect(
        page.getByRole('heading', { name: 'Website footer', exact: true }),
    ).toBeVisible();
    await choosePhoto(page, 'footer_logo', 'footer-photo');
    const logo = await page
        .locator('input[name="settings[footer_logo]"]')
        .inputValue();
    expect(logo).toMatch(/^\d+$/);
    await typeBold(page, '#about_us_description', 'Fresh dates daily');
    await page.locator('#frontend_copyright_text').click();
    await page.keyboard.type('Browser store rights');
    await page.locator('#contact_phone').fill('+31 20 555 0101');
    await page.locator('#contact_email').fill('shop@example.com');
    await page.locator('#widget_one').fill('Useful links');
    await page.getByRole('button', { name: 'Add link', exact: true }).click();
    await page.locator('#footer-link-label-0').fill('Browser help');
    await page.locator('#footer-link-url-0').fill('/help');
    await page.locator('#show_social_links').check();
    await page
        .locator('#facebook_link')
        .fill('https://facebook.example.com/store');
    expect(await documentViolations(page)).toEqual([]);

    const saved = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/website/footer' &&
            r.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Save footer', exact: true })
        .click();
    // Inertia answers the redirect of a PUT with 303.
    expect((await saved).status()).toBe(303);
    await expect(
        page.getByRole('button', { name: 'Save footer', exact: true }),
    ).toBeEnabled();
    await page.reload();
    await expect(
        page.locator('input[name="settings[footer_logo]"]'),
    ).toHaveValue(logo);
    await expect(
        page.locator('#footer_logo').locator('..').locator('img'),
    ).toHaveAttribute('src', /\/uploads\/all\//);
    await expect(page.locator('#about_us_description strong')).toHaveText(
        'Fresh dates daily',
    );
    await expect(page.locator('#contact_phone')).toHaveValue('+31 20 555 0101');
    await expect(page.locator('#footer-link-label-0')).toHaveValue(
        'Browser help',
    );
    await expect(page.locator('#show_social_links')).toBeChecked();
    expect(
        stored(
            'footer',
            "['about' => get_setting('about_us_description', null, 'en'), 'copyright' => get_setting('frontend_copyright_text', null, 'en'), 'logo' => get_setting('footer_logo'), 'labels' => get_setting('widget_one_labels', null, 'en'), 'links' => get_setting('widget_one_links'), 'social' => get_setting('show_social_links')]",
        ),
    ).toEqual({
        about: '<p><strong>Fresh dates daily</strong></p>',
        copyright: '<p>Browser store rights</p>',
        logo,
        labels: '["Browser help"]',
        links: '["\\/help"]',
        social: 'on',
    });

    await page.goto('/temp');
    const footer = page.locator('footer').first();
    await expect(
        page.getByText('Fresh dates daily', { exact: true }).first(),
    ).toBeAttached();
    await expect(page.getByText('Browser store rights').first()).toBeAttached();
    await expect(
        page.locator('a[href="/help"]', { hasText: 'Browser help' }).first(),
    ).toBeAttached();
    await expect(
        page.locator('a[href="https://facebook.example.com/store"]').first(),
    ).toBeAttached();
    await expect(
        page.locator(`img[data-src*="/uploads/all/"]`).first(),
    ).toBeAttached();
    await expect(footer).toBeAttached();
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('appearance saves through its typed writer and the scripts through the compatibility endpoint, and the storefront renders both', async ({
    page,
}) => {
    resetWebsite();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'icon-photo');
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/website/appearance');
    await expect(
        page.getByRole('heading', { name: 'Website appearance', exact: true }),
    ).toBeVisible();
    await page.locator('#website_name').fill('Browser Store');
    await page.locator('#site_motto').fill('Fresh food');
    await choosePhoto(page, 'site_icon', 'icon-photo');
    await page.locator('#base_color').fill('#336699');
    await typeBold(page, '#cookies_agreement_text', 'Browser cookies notice');
    await page.locator('#show_cookies_agreement').check();
    await page.locator('#website_popup_content').click();
    await page.keyboard.type('Browser popup welcome');
    await page.locator('#show_website_popup').check();
    const saved = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/website/appearance' &&
            r.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Save appearance', exact: true })
        .click();
    expect((await saved).status()).toBe(303);

    // The scripts are plain code fields posting to the compatibility endpoint.
    const header =
        '<meta name="e2e-header-script" content="saved"><style>.e2e-script { color: #336699 }</style>';
    const footerScript =
        '<span id="e2e-footer-script" hidden>footer script</span>';
    await expect(page.locator('#header_script')).toHaveJSProperty(
        'tagName',
        'TEXTAREA',
    );
    await page.locator('#header_script').fill(header);
    await page.locator('#footer_script').fill(footerScript);
    const scripts = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname ===
                '/temp/admin/website/scripts/compatibility' &&
            r.request().method() === 'POST',
    );
    await page
        .getByRole('button', { name: 'Save scripts', exact: true })
        .click();
    expect((await scripts).status()).toBe(302);
    await expect(
        page.getByRole('button', { name: 'Save scripts', exact: true }),
    ).toBeEnabled();

    await page.reload();
    await expect(page.locator('#website_name')).toHaveValue('Browser Store');
    await expect(page.locator('#base_color')).toHaveValue('#336699');
    await expect(
        page.locator('input[name="settings[site_icon]"]'),
    ).not.toHaveValue('');
    await expect(page.locator('#cookies_agreement_text strong')).toHaveText(
        'Browser cookies notice',
    );
    await expect(page.locator('#show_website_popup')).toBeChecked();
    await expect(page.locator('#header_script')).toHaveValue(header);
    await expect(page.locator('#footer_script')).toHaveValue(footerScript);
    expect(await documentViolations(page)).toEqual([]);

    // A script tag still meets the unchanged WAF: refused, nothing written.
    await page
        .locator('#header_script')
        .fill('<script>window.e2e = 1</script>');
    const refused = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname ===
                '/temp/admin/website/scripts/compatibility' &&
            r.request().method() === 'POST',
    );
    await page
        .getByRole('button', { name: 'Save scripts', exact: true })
        .click();
    expect((await refused).status()).toBe(403);
    expect(
        stored(
            'appearance',
            "['header' => get_setting('header_script'), 'footer' => get_setting('footer_script'), 'name' => get_setting('website_name'), 'cookies' => get_setting('cookies_agreement_text'), 'popup' => get_setting('show_website_popup'), 'icon' => get_setting('site_icon')]",
        ),
    ).toMatchObject({
        header,
        footer: footerScript,
        name: 'Browser Store',
        cookies: '<p><strong>Browser cookies notice</strong></p>',
        popup: 'on',
    });

    await page.goto('/temp');
    await expect(page).toHaveTitle('Browser Store | Fresh food');
    await expect(
        page.locator('head meta[name="e2e-header-script"]'),
    ).toHaveAttribute('content', 'saved');
    await expect(page.locator('#e2e-footer-script')).toBeAttached();
    await expect(page.locator('link[rel="icon"]')).toHaveAttribute(
        'href',
        /\/uploads\/all\//,
    );
    await expect(
        page.getByText('Browser cookies notice', { exact: true }),
    ).toBeAttached();
    await expect(page.getByText('Browser popup welcome')).toBeAttached();
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('a custom page is created, edited per language and deleted with native confirms, and the public page shows it', async ({
    page,
}) => {
    resetWebsite();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/website/pages');
    const createVisit = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname ===
                '/temp/admin/website/custom-pages/create' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page.getByRole('link', { name: 'Add new page', exact: true }).click();
    expect((await createVisit).status()).toBe(200);
    await page.locator('#title').fill('Browser page');
    await page.locator('#slug').pressSequentially('Browser Page!');
    await expect(page.locator('#slug')).toHaveValue('Browser-Page');
    await typeBold(page, '#content', 'Browser page body');
    await page.locator('#meta_title').fill('Browser meta');
    const created = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/website/custom-pages' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: 'Save page', exact: true }).click();
    expect((await created).status()).toBe(302);
    await expect(page).toHaveURL(/\/temp\/admin\/website\/pages$/);
    const row = page.getByRole('row', { name: /Browser page/ });
    await expect(row).toBeVisible();

    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page).toHaveURL(
        /\/temp\/admin\/website\/custom-pages\/edit\/Browser-Page$/,
    );
    await expect(page.locator('#content strong')).toHaveText(
        'Browser page body',
    );
    await page.locator('#content strong').click();
    await press(page, 'End', '#content');
    await page.keyboard.type(' edited');
    const updated = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/website\/custom-pages\/\d+$/.test(
                new URL(r.url()).pathname,
            ) && r.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Update page', exact: true })
        .click();
    expect((await updated).status()).toBe(303);
    await expect(page).toHaveURL(/\/temp\/admin\/website\/pages$/);

    await page.goto('/temp/Browser-Page');
    await expect(
        page.getByText('Browser page body edited', { exact: true }),
    ).toBeVisible();
    expect(
        stored(
            'page',
            "App\\Models\\Page::query()->where('slug', 'Browser-Page')->first(['type', 'title', 'content', 'meta_title'])?->only(['type', 'title', 'content', 'meta_title'])",
        ),
    ).toEqual({
        type: 'custom_page',
        title: 'Browser page',
        content: '<p><strong>Browser page body edited</strong></p>',
        meta_title: 'Browser meta',
    });

    await page.goto('/temp/admin/website/pages');
    const deleteButton = page
        .getByRole('row', { name: /Browser page/ })
        .getByRole('button', { name: 'Delete', exact: true });
    page.once('dialog', (dialog) => dialog.dismiss());
    await deleteButton.click();
    await expect(page.getByRole('row', { name: /Browser page/ })).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await deleteButton.click();
    await expect(page.getByRole('row', { name: /Browser page/ })).toHaveCount(
        0,
    );
    expect(
        stored(
            'deleted',
            "App\\Models\\Page::query()->where('slug', 'Browser-Page')->exists()",
        ),
    ).toBe(false);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('script and handler payloads in footer and page rich text stay inert, stored or pasted', async ({
    page,
}) => {
    const hostile =
        '<p>Safe text</p><img src="x" onerror="alert(\'stored\')"><script>alert(\'stored\')</script>' +
        '<a href="javascript:alert(\'stored\')">Bad link</a>';
    resetWebsite({ 'about_us_description@en': hostile });
    seedPage('browser-hostile', hostile);
    const dialogs: string[] = [];
    page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message());
        await dialog.dismiss();
    });
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);

    await page.goto('/temp/admin/website/footer?lang=en');
    await expect(page.locator('#about_us_description')).toContainText(
        'Safe text',
    );
    expect(await page.locator('#about_us_description').innerHTML()).not.toMatch(
        /onerror|<script|javascript:/,
    );
    await page.locator('#about_us_description p').first().click();
    await press(page, 'End', '#about_us_description');
    await page.evaluate(() => {
        const data = new DataTransfer();
        data.setData(
            'text/html',
            '<p>Pasted <img src="x" onerror="alert(\'pasted\')"></p><script>alert(\'pasted\')</script>',
        );
        data.setData('text/plain', 'Pasted');
        document.querySelector('#about_us_description')!.dispatchEvent(
            new ClipboardEvent('paste', {
                clipboardData: data,
                bubbles: true,
                cancelable: true,
            }),
        );
    });
    await expect(page.locator('#about_us_description')).toContainText('Pasted');
    await page
        .getByRole('button', { name: 'Save footer', exact: true })
        .click();
    await expect
        .poll(
            () =>
                stored(
                    'hostile-footer',
                    "get_setting('about_us_description', null, 'en')",
                ) as string,
        )
        .toContain('Pasted');
    expect(
        stored(
            'hostile-footer',
            "get_setting('about_us_description', null, 'en')",
        ),
    ).not.toMatch(/onerror|<script|javascript:|alert/);

    await page.goto('/temp/admin/website/custom-pages/edit/browser-hostile');
    await expect(page.locator('#content')).toContainText('Safe text');
    expect(await page.locator('#content').innerHTML()).not.toMatch(
        /onerror|<script|javascript:/,
    );
    await page
        .getByRole('button', { name: 'Update page', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/website\/pages$/);
    expect(
        stored(
            'hostile-page',
            "App\\Models\\Page::query()->where('slug', 'browser-hostile')->value('content')",
        ),
    ).not.toMatch(/onerror|<script|javascript:|alert/);

    await page.goto('/temp');
    await expect(page.getByText('Pasted').first()).toBeAttached();
    await page.goto('/temp/browser-hostile');
    await expect(
        page.locator('div.mw-100.text-left').getByText('Safe text', {
            exact: true,
        }),
    ).toBeVisible();
    expect(dialogs).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('the website screens work in Arabic RTL with dark contrast and a keyboard-only, mirrored toolbar', async ({
    page,
}) => {
    resetWebsite({
        'about_us_description@ar': '<p>نبذة <strong>عنا</strong></p>',
    });
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/website/pages');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'صفحات الموقع', exact: true }),
    ).toBeVisible();

    await page.goto('/temp/admin/website/footer?lang=ar');
    await expect(
        page.getByRole('heading', { name: 'تذييل الموقع', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#about_us_description strong')).toHaveText(
        'عنا',
    );
    await expect(
        page.locator('[role="toolbar"][aria-controls="about_us_description"]'),
    ).toHaveAttribute('aria-label', 'التنسيق');
    await page
        .locator('#footer_logo')
        .locator('..')
        .getByRole('button', { name: 'اختيار الملفات', exact: true })
        .focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowLeft');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#about_us_description')).toBeFocused();

    for (const selector of [
        '#about_us_description',
        '#contact_phone',
        '[role="toolbar"][aria-controls="about_us_description"] select',
    ]) {
        const colors = await colorsOf(page, selector);
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.foreground, colors.background),
        ).toBeGreaterThanOrEqual(4.5);
    }

    await page.goto('/temp/admin/website/appearance');
    await expect(
        page.getByRole('heading', { name: 'مظهر الموقع', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#header_script')).toHaveAttribute('dir', 'ltr');
    const scriptColors = await colorsOf(page, '#header_script');
    expect(
        contrastRatio(scriptColors.foreground, scriptColors.background),
    ).toBeGreaterThanOrEqual(4.5);
    expect(await documentViolations(page)).toEqual([]);
    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});
