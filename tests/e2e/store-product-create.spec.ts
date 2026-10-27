import type { Page } from '@playwright/test';
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
 * The physical product create form is Inertia, on the shared editor, the
 * canonical upload picker and the typed variant endpoint
 * (.ai/archive/store-backoffice/changes-20261013.md §22), under the strict CSP.
 */

const names = [
    'Browser pantry oil',
    'Browser variant oil',
    'Browser hostile oil',
];

/**
 * A physical category, a brand, two colours, an attribute with two values,
 * an active tax and the settings that show the shipping, tax and
 * cash-on-delivery sections; no product left from an earlier run.
 */
function resetProductForm(): void {
    seedStoreFixtures(String.raw`
        $settings = ['Setup_Shipping_section' => '1', 'shipping_type' => 'product_wise_shipping', 'VAT_TAX_section' => '1', 'cash_payment' => '1', 'Flash_deals' => '0', 'In_House_Products_Section' => '1'];
        foreach ($settings as $type => $value) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => $type], ['value' => $value]);
        }
        foreach (App\Models\Product::where('name', 'like', 'Browser % oil%')->whereIn('name', [${names.map(php).join(', ')}])->pluck('id') as $id) {
            foreach (['product_translations', 'product_stocks', 'product_taxes', 'product_variations', 'product_barcodes', 'flash_deal_products', 'wms_product_settings'] as $table) {
                Illuminate\Support\Facades\DB::table($table)->where('product_id', $id)->delete();
            }
            App\Models\Product::whereKey($id)->delete();
        }
        App\Models\Category::where('slug', 'browser-pantry')->delete();
        App\Models\Category::forceCreate(['name' => 'Browser pantry', 'parent_id' => 0, 'level' => 0, 'slug' => 'browser-pantry', 'digital' => 0]);
        Illuminate\Support\Facades\DB::table('brands')->updateOrInsert(['slug' => 'browser-brand'], ['name' => 'Browser brand']);
        Illuminate\Support\Facades\DB::table('colors')->whereIn('name', ['Browser Crimson', 'Browser Navy'])->delete();
        Illuminate\Support\Facades\DB::table('colors')->insert([['name' => 'Browser Crimson', 'code' => '#9F1239'], ['name' => 'Browser Navy', 'code' => '#1E3A8A']]);
        $size = Illuminate\Support\Facades\DB::table('attributes')->where('name', 'Browser size')->value('id')
            ?? Illuminate\Support\Facades\DB::table('attributes')->insertGetId(['name' => 'Browser size']);
        Illuminate\Support\Facades\DB::table('attribute_values')->where('attribute_id', $size)->delete();
        Illuminate\Support\Facades\DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'Small'], ['attribute_id' => $size, 'value' => 'Extra Large']]);
        Illuminate\Support\Facades\DB::table('taxes')->whereIn('name', ['Browser VAT', 'Browser old levy'])->delete();
        Illuminate\Support\Facades\DB::table('taxes')->update(['tax_status' => 0]);
        Illuminate\Support\Facades\DB::table('taxes')->insert([['name' => 'Browser VAT', 'tax_status' => 1], ['name' => 'Browser old levy', 'tax_status' => 0]]);
    `);
}

/**
 * The storefront product page lists the owner's other products and, in this
 * strict-mode harness, lazy-loads their translations, which fails for a
 * product without a slug (inherited; §21). The form gives a new product the
 * store's own admin account as its owner, as ProductService did (Pest pins
 * that); the storefront check moves it to an owner of its own first.
 */
function giveOwnOwner(name: string): void {
    seedStoreFixtures(String.raw`
        $owner = App\Models\StoreUser::query()->firstOrNew(['email' => 'browser-product-owner@example.com']);
        $owner->forceFill(['name' => 'Browser product owner', 'user_type' => 'admin', 'password' => bcrypt(Illuminate\Support\Str::random(16))])->save();
        App\Models\Product::query()->where('name', ${php(name)})->update(['user_id' => $owner->id]);
    `);
}

/** Fills the fields every product needs. */
async function fillRequired(page: Page, name: string): Promise<void> {
    await page.locator('#name').fill(name);
    await page
        .locator('#category_id')
        .selectOption({ label: 'Browser pantry' });
    await page.locator('#unit').fill('bottle');
    await page.locator('#unit_price').fill('12.50');
}

async function save(page: Page, button: string): Promise<number> {
    const created = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/products/store' &&
            r.request().method() === 'POST',
    );
    await page.getByRole('button', { name: button, exact: true }).click();

    return (await created).status();
}

test('a simple product is created from the in-house list with editor content, a canonical thumbnail, a tax and a discount range, then listed and shown on the storefront', async ({
    page,
}) => {
    resetProductForm();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'product-thumbnail');
    const reports = await watchCsp(page);

    await expectStrictPolicy(page, '/temp/admin/products/admin');
    const createVisit = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/admin/products/create' &&
            r.request().headers()['x-inertia'] === 'true',
    );
    await page
        .locator('#main')
        .getByRole('link', { name: 'Add new product', exact: true })
        .click();
    expect((await createVisit).status()).toBe(200);
    await expect(
        page.getByRole('heading', { name: 'Add new product', exact: true }),
    ).toBeVisible();
    await expect(
        page.locator('#category_id option', { hasText: 'Browser pantry' }),
    ).toHaveCount(1);
    await expect(page.getByText('Browser old levy')).toHaveCount(0);

    await fillRequired(page, 'Browser pantry oil');
    await page.locator('#brand_id').selectOption({ label: 'Browser brand' });
    await page.locator('#tags').fill('oil, pantry ,oil');
    await pickUploads(page, 'thumbnail_img', ['product-thumbnail']);
    await page.locator('#current_stock').fill('40');
    await page.locator('#sku').fill('BR-OIL');
    await page
        .getByRole('button', { name: 'Add barcode', exact: true })
        .click();
    await page
        .getByLabel('Product barcodes 1', { exact: true })
        .fill('BROWSER-8712345');
    await page
        .locator('#date_range')
        .fill('01-11-2026 08:00:00 to 30-11-2026 20:30:00');
    await page.locator('#discount').fill('1.50');
    const vat = page.getByRole('spinbutton', {
        name: 'Browser VAT required',
        exact: true,
    });
    await expect(vat).toHaveAttribute('required', '');
    const vatId = await vat.getAttribute('id');
    await vat.fill('21');
    await page
        .locator(`#${vatId!.replace('tax_', 'tax_type_')}`)
        .selectOption('percent');

    // Keyboard only: the toolbar is one tab stop between the last tax type
    // and the description.
    await page.locator(`#${vatId!.replace('tax_', 'tax_type_')}`).focus();
    await page.keyboard.press('Tab');
    expect(await focusedToolbarItem(page)).toBe('blockStyle');
    await page.keyboard.press('Tab');
    await expect(page.locator('#description')).toBeFocused();
    await page.keyboard.type('Cold pressed');
    await press(page, 'Shift+Home', '#description', 'Cold pressed');
    await page.keyboard.press('Alt+F10');
    await page.keyboard.press('ArrowRight');
    expect(await focusedToolbarItem(page)).toBe('bold');
    await page.keyboard.press('Enter');
    await expect(page.locator('#description strong')).toHaveText(
        'Cold pressed',
    );
    expect(await documentViolations(page)).toEqual([]);

    expect([302, 303]).toContain(await save(page, 'Save & publish'));
    await expect(page).toHaveURL(/\/temp\/admin\/products\/admin$/);
    const product = storedProduct(
        'Browser pantry oil',
        "['published' => $product->published, 'digital' => $product->digital, 'added_by' => $product->added_by, 'description' => $product->description, 'tags' => $product->tags, 'thumbnail' => $product->thumbnail_img, 'start' => $product->discount_start_date, 'end' => $product->discount_end_date, 'discount' => (float) $product->discount, 'slug' => $product->slug, 'taxes' => Illuminate\\Support\\Facades\\DB::table('product_taxes')->where('product_id', $product->id)->get(['tax', 'tax_type'])->map(fn ($t) => [(float) $t->tax, $t->tax_type])->all(), 'stocks' => Illuminate\\Support\\Facades\\DB::table('product_stocks')->where('product_id', $product->id)->get(['variant', 'sku', 'qty'])->map(fn ($s) => [$s->variant, $s->sku, (int) $s->qty])->all(), 'barcodes' => Illuminate\\Support\\Facades\\DB::table('product_barcodes')->where('product_id', $product->id)->pluck('barcode')->all(), 'translation' => App\\Models\\ProductTranslation::where('product_id', $product->id)->where('lang', 'en')->value('name')]",
    ) as Record<string, unknown>;
    expect(product).toMatchObject({
        published: true,
        digital: 0,
        added_by: 'admin',
        description: '<p><strong>Cold pressed</strong></p>',
        tags: 'oil,pantry',
        discount: 1.5,
        start: Date.UTC(2026, 10, 1, 8) / 1000,
        end: Date.UTC(2026, 10, 30, 20, 30) / 1000,
        slug: 'browser-pantry-oil',
        taxes: [[21, 'percent']],
        stocks: [['', 'BR-OIL', 40]],
        barcodes: ['BROWSER-8712345'],
        translation: 'Browser pantry oil',
    });
    expect(String(product.thumbnail)).toMatch(/^\d+$/);

    await page.goto(
        '/temp/admin/products/admin?active=1&for_sale=1&search=Browser+pantry',
    );
    await expect(
        page.getByRole('row', { name: /Browser pantry oil/ }),
    ).toBeVisible();
    giveOwnOwner('Browser pantry oil');
    await page.goto('/temp/product/browser-pantry-oil');
    await expect(
        page.getByRole('heading', { name: 'Browser pantry oil' }).first(),
    ).toBeVisible();
    await expect(
        page.locator('strong', { hasText: 'Cold pressed' }).first(),
    ).toBeVisible();
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('a variant product generates its SKU rows from colours and attribute values and saves unpublished', async ({
    page,
}) => {
    resetProductForm();
    await loginAsStoreAdmin(page);
    await uploadPhoto(page, 'variant-photo');
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/products/create');
    await fillRequired(page, 'Browser variant oil');

    await page.getByLabel('Use colours', { exact: true }).check();
    await page.getByLabel('Browser Navy', { exact: true }).check();
    await page.getByLabel('Browser Crimson', { exact: true }).check();
    const values = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname ===
            '/temp/admin/products/add-more-choice-option',
    );
    await page.getByLabel('Browser size', { exact: true }).check();
    expect((await values).status()).toBe(200);
    await page.getByLabel('Extra Large', { exact: true }).check();
    const rows = page.waitForResponse(
        async (r) =>
            new URL(r.url()).pathname ===
                '/temp/admin/products/variant-combinations' &&
            (await r.json()).length === 4,
    );
    await page.getByLabel('Small', { exact: true }).check();
    expect((await rows).status()).toBe(200);

    // Colours by name, list order; values as the legacy table named them.
    await expect(page.locator('tbody th[scope="row"] span')).toHaveText([
        'Browser Crimson-Small',
        'Browser Crimson-ExtraLarge',
        'Browser Navy-Small',
        'Browser Navy-ExtraLarge',
    ]);
    await expect(page.locator('input[name="variants[0][price]"]')).toHaveValue(
        '12.50',
    );
    await expect(page.locator('input[name="variants[0][qty]"]')).toHaveValue(
        '10',
    );
    await expect(page.locator('#current_stock')).toBeHidden();
    await page.locator('input[name="variants[1][price]"]').fill('15');
    await page.locator('input[name="variants[1][sku]"]').fill('CR-XL');
    await page.locator('input[name="variants[3][qty]"]').fill('2');
    await pickUploads(page, 'variant_image_0', ['variant-photo']);
    await page.locator('#date_range').fill('');
    await page.locator('#discount').fill('0');

    expect([302, 303]).toContain(await save(page, 'Save & unpublish'));
    await expect(page).toHaveURL(/\/temp\/admin\/products\/admin$/);
    const product = storedProduct(
        'Browser variant oil',
        "['published' => $product->published, 'variant_product' => $product->variant_product, 'colors' => json_decode($product->colors), 'choice_options' => json_decode($product->choice_options, true), 'stocks' => Illuminate\\Support\\Facades\\DB::table('product_stocks')->where('product_id', $product->id)->orderBy('id')->get(['variant', 'price', 'sku', 'qty', 'image'])->map(fn ($s) => [$s->variant, (float) $s->price, $s->sku, (int) $s->qty, $s->image !== null])->all()]",
    ) as Record<string, unknown>;
    expect(product).toMatchObject({
        published: false,
        variant_product: 1,
        colors: ['#9F1239', '#1E3A8A'],
        stocks: [
            ['Browser Crimson-Small', 12.5, null, 10, true],
            ['Browser Crimson-ExtraLarge', 15, 'CR-XL', 10, false],
            ['Browser Navy-Small', 12.5, null, 10, false],
            ['Browser Navy-ExtraLarge', 12.5, null, 2, false],
        ],
    });
    expect(
        (product.choice_options as { values: string[] }[])[0].values,
    ).toEqual(['Small', 'Extra Large']);
    await page.goto(
        '/temp/admin/products/admin?for_sale=1&search=Browser+variant',
    );
    await expect(
        page.getByRole('row', { name: /Browser variant oil/ }),
    ).toBeVisible();
    expect(await documentViolations(page)).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('pasted script payloads stay inert in the product editor and on the storefront', async ({
    page,
}) => {
    resetProductForm();
    const dialogs: string[] = [];
    page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message());
        await dialog.dismiss();
    });
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/products/create');
    await fillRequired(page, 'Browser hostile oil');
    await page.locator('#description').click();
    await page.evaluate(() => {
        const data = new DataTransfer();
        data.setData(
            'text/html',
            '<p>Pasted <img src="x" onerror="alert(\'pasted\')"><a href="javascript:alert(\'link\')">link</a></p><script>alert(\'pasted\')</script>',
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
        /onerror|<script|javascript:/,
    );
    expect([302, 303]).toContain(await save(page, 'Save & publish'));
    const body = storedProduct(
        'Browser hostile oil',
        '$product->description',
    ) as string;
    expect(body).toContain('Pasted');
    expect(body).not.toMatch(/onerror|<script|javascript:|alert/);

    giveOwnOwner('Browser hostile oil');
    await page.goto('/temp/product/browser-hostile-oil');
    await expect(page.getByText('Pasted').first()).toBeVisible();
    expect(dialogs).toEqual([]);
    expect(reports.filter((report) => report.includes('/temp/admin/'))).toEqual(
        [],
    );
});

test('the product create form works in Arabic RTL with dark contrast and a keyboard-only, mirrored toolbar', async ({
    page,
}) => {
    resetProductForm();
    ensureStoreRtlLanguage();
    await loginAsStoreAdmin(page);
    const reports = await watchCsp(page);
    await page.goto('/temp/admin/products/admin');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    await page.goto('/temp/admin/products/create');
    await expect(
        page.getByRole('heading', { name: 'إضافة منتج جديد', exact: true }),
    ).toBeVisible();

    for (const legend of [
        'معلومات المنتج',
        'صور المنتج',
        'تنويعات المنتج',
        'السعر والمخزون',
        'وصف المنتج',
        'المخزون والشحن والظهور',
    ]) {
        await expect(
            page.locator('legend', { hasText: legend }).first(),
        ).toBeVisible();
    }

    await expect(page.locator('#date_range')).toHaveAttribute('dir', 'ltr');
    await expect(
        page.locator('[role="toolbar"][aria-controls="description"]'),
    ).toHaveAttribute('aria-label', 'التنسيق');
    await page.locator('[id^="tax_type_"]').last().focus();
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
        '#category_id',
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
