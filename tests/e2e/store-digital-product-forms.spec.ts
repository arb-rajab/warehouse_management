import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import {
    colorsOf,
    documentViolations,
    expectStrictPolicy,
    focusedToolbarItem,
    php,
    pickUploads,
    press,
    uploadFixture,
    uploadPhoto,
    watchCsp,
} from './support/editor';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
    storedProduct,
} from './support/storeFixtures';

/**
 * Digital product create/edit use the shared Tiptap editor and the canonical
 * upload picker (.ai/archive/store-backoffice/changes-20261013.md §21) under the
 * strict Inertia CSP.
 */

const names = ['Browser bread guide', 'Browser hostile guide'];

/** A digital category tree, an active and an inactive tax, no old products. */
function resetDigitalForms(products: Record<string, string> = {}): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type'=>'Digital_Products_Section'],['value'=>'1']);
        foreach (App\Models\Product::whereIn('name', [${names.map(php).join(', ')}])->pluck('id') as $id) {
            foreach (['product_translations', 'product_stocks', 'product_taxes', 'wms_product_settings'] as $table) {
                Illuminate\Support\Facades\DB::table($table)->where('product_id', $id)->delete();
            }
            App\Models\Product::whereKey($id)->delete();
        }
        App\Models\Category::whereIn('slug', ['browser-e-books', 'browser-recipes', 'browser-oils'])->delete();
        $root = App\Models\Category::forceCreate(['name' => 'Browser e-books', 'parent_id' => 0, 'level' => 0, 'slug' => 'browser-e-books', 'digital' => 1]);
        App\Models\Category::forceCreate(['name' => 'Browser recipes', 'parent_id' => $root->id, 'level' => 1, 'slug' => 'browser-recipes', 'digital' => 0]);
        App\Models\Category::forceCreate(['name' => 'Browser oils', 'parent_id' => 0, 'level' => 0, 'slug' => 'browser-oils', 'digital' => 0]);
        Illuminate\Support\Facades\DB::table('taxes')->whereIn('name', ['Browser VAT', 'Browser old levy'])->delete();
        Illuminate\Support\Facades\DB::table('taxes')->update(['tax_status' => 0]);
        Illuminate\Support\Facades\DB::table('taxes')->insert([['name' => 'Browser VAT', 'tax_status' => 1], ['name' => 'Browser old levy', 'tax_status' => 0]]);
        // A storefront page lists its owner's other products; an owner of
        // its own keeps that list to products with a slug.
        $owner = App\Models\StoreUser::query()->firstOrNew(['email' => 'browser-digital-owner@example.com']);
        $owner->forceFill(['name' => 'Browser digital owner', 'user_type' => 'admin', 'password' => bcrypt(Illuminate\Support\Str::random(16))])->save();
        foreach ([${Object.entries(products)
            .map(([name, body]) => `${php(name)} => ${php(body)}`)
            .join(', ')}] as $name => $body) {
            $product = App\Models\Product::forceCreate(['name' => $name, 'ar_name' => '', 'slug' => Illuminate\Support\Str::slug($name), 'digital' => 1, 'added_by' => 'admin', 'user_id' => $owner->id, 'category_id' => $root->id, 'unit_price' => 5, 'purchase_price' => 1, 'discount' => 0, 'discount_type' => 'amount', 'published' => 1, 'description' => $body]);
            App\Models\ProductTranslation::create(['product_id' => $product->id, 'lang' => 'en', 'name' => $name, 'description' => $body]);
            Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => '', 'sku' => '', 'price' => 5, 'qty' => 0]);
        }
    `);
}

test('a digital product is created with editor content, canonical uploads, taxes and a discount range, edited per language and downloaded from the list', async ({
    page,
}) => {
    resetDigitalForms();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'digital-photo-one');
    await uploadPhoto(page, 'digital-photo-two');
    await uploadFixture(page, 'digital-guide', {
        extension: 'txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('The browser bread guide'),
    });
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/digitalproducts');
    const createVisit = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname ===
                '/temp/admin/digitalproducts/create' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page
        .getByRole('link', { name: 'Add digital product', exact: true })
        .click();
    expect((await createVisit).status()).toBe(200);
    await expect(
        page.getByRole('heading', {
            name: 'Add new digital product',
            exact: true,
        }),
    ).toBeVisible();
    await expect(page.locator('#category_id option')).toHaveText([
        'Select a category',
        'Browser e-books',
        '-- Browser recipes',
    ]);
    const vat = page.getByRole('spinbutton', {
        name: 'Browser VAT required',
        exact: true,
    });
    await expect(vat).toHaveCount(1);
    await expect(vat).toHaveAttribute('required', '');
    await expect(page.getByText('Browser old levy')).toHaveCount(0);

    await page.locator('#name').fill('Browser bread guide');
    await page
        .locator('#category_id')
        .selectOption({ label: '-- Browser recipes' });
    await page.locator('#tags').fill('bread, baking ,bread');
    await pickUploads(page, 'file', ['digital-guide']);
    await pickUploads(page, 'photos', [
        'digital-photo-one',
        'digital-photo-two',
    ]);
    await pickUploads(page, 'thumbnail_img', ['digital-photo-two']);
    await pickUploads(page, 'meta_img', ['digital-photo-one']);
    await page.locator('#meta_title').fill('Bread guide');
    await page.locator('#meta_description').fill('How to bake bread');
    await page.locator('#unit_price').fill('19.99');
    await page.locator('#purchase_price').fill('10');
    const vatId = await vat.getAttribute('id');
    await vat.fill('21');
    await page
        .locator(`#${vatId!.replace('tax_', 'tax_type_')}`)
        .selectOption('percent');
    await page
        .locator('#date_range')
        .fill('01-11-2026 08:00:00 to 30-11-2026 20:30:00');
    await page.locator('#discount').fill('2.5');
    await page.locator('#discount_type').selectOption('percent');

    // Keyboard only: the toolbar is one tab stop between the discount type
    // and the description.
    await page.locator('#discount_type').focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#description')).toBeFocused();
    await page.keyboard.type('Knead well');
    await press(page, 'Shift+Home', '#description', 'Knead well');
    await page.keyboard.press('Alt+F10');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('Enter');
    await expect(page.locator('#description strong')).toHaveText('Knead well');
    expect(await documentViolations(page)).toEqual([]);

    const created = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/digitalproducts' &&
            r.request().method() === 'POST',
    );
    await page
        .getByRole('button', { name: 'Save product', exact: true })
        .click();
    expect([302, 303]).toContain((await created).status());
    await expect(page).toHaveURL(/\/temp\/admin\/digitalproducts$/);
    const product = storedProduct(
        'Browser bread guide',
        "['description' => $product->description, 'tags' => $product->tags, 'photos' => $product->photos, 'file' => $product->file_name, 'start' => $product->discount_start_date, 'end' => $product->discount_end_date, 'discount_type' => $product->discount_type, 'taxes' => Illuminate\\Support\\Facades\\DB::table('product_taxes')->where('product_id', $product->id)->get(['tax', 'tax_type'])->map(fn ($t) => [(float) $t->tax, $t->tax_type])->all(), 'stocks' => Illuminate\\Support\\Facades\\DB::table('product_stocks')->where('product_id', $product->id)->count(), 'translation' => App\\Models\\ProductTranslation::where('product_id', $product->id)->where('lang', 'en')->value('name')]",
    ) as Record<string, unknown>;
    expect(product).toMatchObject({
        description: '<p><strong>Knead well</strong></p>',
        tags: 'bread,baking',
        discount_type: 'percent',
        start: Date.UTC(2026, 10, 1, 8) / 1000,
        end: Date.UTC(2026, 10, 30, 20, 30) / 1000,
        taxes: [[21, 'percent']],
        stocks: 1,
        translation: 'Browser bread guide',
    });
    expect(String(product.photos)).toMatch(/^\d+,\d+$/);
    expect(String(product.file)).toMatch(/^\d+$/);

    // The list downloads the picked file through the encrypted id.
    const row = page.getByRole('row', { name: /Browser bread guide/ });
    const download = page.waitForEvent('download');
    await row.getByRole('link', { name: 'Download', exact: true }).click();
    const file = await (await download).path();
    expect(readFileSync(file, 'utf8')).toBe('The browser bread guide');

    const editVisit = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/digitalproducts\/edit\/\d+$/.test(
                new URL(r.url()).pathname,
            ) && r.request().headers()['x-inertia'] === 'true',
    );
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    expect((await editVisit).status()).toBe(200);
    await expect(page.locator('#name')).toHaveValue('Browser bread guide');
    await expect(page.locator('#date_range')).toHaveValue(
        '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
    );
    await expect(page.locator('#description strong')).toHaveText('Knead well');
    await expect(
        page.locator('label[for="photos"]').locator('..').locator('img'),
    ).toHaveCount(2);
    await expect(page.locator('label[for="file"]').locator('..')).toContainText(
        'digital-guide',
    );

    // The Arabic tab edits only that language's name and description.
    const arabicVisit = page.waitForResponse(
        (r) =>
            new URL(r.url()).search === '?lang=ar' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page.getByRole('link', { name: 'Arabic', exact: true }).click();
    expect((await arabicVisit).status()).toBe(200);
    await expect(
        page.getByRole('link', { name: 'Arabic', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.locator('#name').fill('دليل الخبز');
    await page.locator('#description').click();
    await press(page, 'Control+a', '#description');
    await page.keyboard.type('اعجن جيدا');
    const updated = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/digitalproducts\/\d+$/.test(
                new URL(r.url()).pathname,
            ) && r.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Update product', exact: true })
        .click();
    expect([302, 303]).toContain((await updated).status());
    await expect(page).toHaveURL(
        /\/temp\/admin\/digitalproducts\/edit\/\d+\?lang=ar$/,
    );
    await expect(page.locator('#name')).toHaveValue('دليل الخبز');
    expect(
        storedProduct(
            'Browser bread guide',
            "[$product->description, App\\Models\\ProductTranslation::where('product_id', $product->id)->orderBy('lang')->pluck('name', 'lang')]",
        ),
    ).toEqual([
        '<p><strong>Knead well</strong></p>',
        { ar: 'دليل الخبز', en: 'Browser bread guide' },
    ]);
    await page.getByRole('link', { name: 'English', exact: true }).click();
    await expect(page.locator('#name')).toHaveValue('Browser bread guide');
    expect(await documentViolations(page)).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('script payloads stay inert in the digital product editor and on the storefront, stored or pasted', async ({
    page,
}) => {
    resetDigitalForms({
        'Browser hostile guide':
            '<p>Safe text</p><img src="x" onerror="alert(\'stored\')"><script>alert(\'stored\')</script><a href="javascript:alert(\'stored\')">Bad link</a>',
    });
    const dialogs: string[] = [];
    page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message());
        await dialog.dismiss();
    });
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);

    await page.goto('/temp/product/browser-hostile-guide');
    await expect(page.getByText('Safe text').first()).toBeVisible();
    expect(await page.content()).not.toMatch(
        /onerror="alert|<script>alert\('stored'\)|javascript:alert/,
    );

    await page.goto('/temp/admin/digitalproducts');
    await page
        .getByRole('row', { name: /Browser hostile guide/ })
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
            '<p>Pasted <img src="x" onerror="alert(\'pasted\')"></p><script>alert(\'pasted\')</script>',
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
        /onerror|<script/,
    );
    const saved = page.waitForResponse(
        (r) =>
            /^\/temp\/admin\/digitalproducts\/\d+$/.test(
                new URL(r.url()).pathname,
            ) && r.request().method() === 'PUT',
    );
    await page
        .getByRole('button', { name: 'Update product', exact: true })
        .click();
    expect([302, 303]).toContain((await saved).status());
    const body = storedProduct(
        'Browser hostile guide',
        '$product->description',
    ) as string;
    expect(body).toContain('Pasted');
    expect(body).not.toMatch(/onerror|<script|javascript:|alert/);

    await page.goto('/temp/product/browser-hostile-guide');
    await expect(page.getByText('Pasted').first()).toBeVisible();
    expect(dialogs).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('the digital product forms work in Arabic RTL with dark contrast and a keyboard-only, mirrored toolbar', async ({
    page,
}) => {
    resetDigitalForms({
        'Browser bread guide': '<p>نص <strong>عريض</strong></p>',
    });
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/digitalproducts');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.goto('/temp/admin/digitalproducts/create');
    await expect(
        page.getByRole('heading', {
            name: 'إضافة منتج رقمي جديد',
            exact: true,
        }),
    ).toBeVisible();

    for (const legend of [
        'عام',
        'الصور',
        'وسوم الميتا',
        'السعر',
        'معلومات المنتج',
    ]) {
        await expect(page.locator('legend', { hasText: legend })).toBeVisible();
    }

    await expect(page.locator('#date_range')).toHaveAttribute('dir', 'ltr');

    await page.goto('/temp/admin/digitalproducts');
    await page
        .getByRole('row', { name: /Browser bread guide/ })
        .getByRole('link', { name: 'تعديل', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'تعديل المنتج الرقمي', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#description strong')).toHaveText('عريض');
    await expect(
        page.locator('[role="toolbar"][aria-controls="description"]'),
    ).toHaveAttribute('aria-label', 'التنسيق');
    await page.locator('#discount_type').focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('ArrowLeft');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#description')).toBeFocused();

    for (const selector of [
        '#description',
        '#name',
        '#discount_type',
        '[role="toolbar"][aria-controls="description"] select',
    ]) {
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
