import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { seedStoreFixtures, storedProduct } from './support/storeFixtures';

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

test('seller account withdrawal and support forms show honest requirements and save successfully', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        Illuminate\Support\Facades\DB::table('shops')->updateOrInsert(['user_id' => $seller->id], ['name' => 'Browser shop', 'slug' => 'browser-shop', 'admin_to_pay' => 100]);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'minimum_seller_amount_withdraw'], ['value' => '10']);
        App\Models\SellerWithdrawRequest::query()->where('user_id', $seller->id)->delete();
    `);
    await loginAsSeller(page);
    await page.goto('/temp/seller/profile');
    let profile = page.locator('form[action*="/seller/profile/update/"]');
    await expect(profile.locator('[data-required-legend]')).toHaveCount(1);
    await expect(profile.locator('#name')).toHaveAccessibleName(
        'Your Name required',
    );
    await profile.locator('#name').fill('');
    await profile.getByRole('button', { name: 'Update Profile' }).click();
    expect(
        await profile
            .locator('#name')
            .evaluate((input: HTMLInputElement) => input.validity.valueMissing),
    ).toBe(true);
    await profile.locator('#name').fill('Browser Seller Changed');
    await profile.locator('#password').fill('123456');
    await profile.locator('#confirm_password').fill('654321');
    await profile.getByRole('button', { name: 'Update Profile' }).click();
    profile = page.locator('form[action*="/seller/profile/update/"]');
    await expect(profile.locator('#name')).toHaveValue(
        'Browser Seller Changed',
    );
    await expect(profile.locator('.text-danger')).toContainText([
        'confirmation',
    ]);
    await expect(profile.locator('#password')).toHaveValue('');
    await profile.getByRole('button', { name: 'Update Profile' }).click();
    await expect(
        page.getByText('Your Profile has been updated successfully!', {
            exact: true,
        }),
    ).toBeVisible();

    await page.goto('/temp/seller/money-withdraw-requests');
    await page.getByText('Send Withdraw Request', { exact: true }).click();
    const withdrawal = page.locator('#request_modal');
    await expect(withdrawal).toBeVisible();
    await expect(withdrawal.locator('#withdraw-amount')).toHaveAccessibleName(
        'Amount required',
    );
    await withdrawal.locator('#withdraw-amount').fill('25.50');
    await withdrawal
        .locator('#withdraw-message')
        .fill('Browser withdrawal note');
    await withdrawal.getByRole('button', { name: 'Send', exact: true }).click();
    // FooTable collapses the message column at this viewport; open its details.
    await page.locator('table.aiz-table .footable-toggle').first().click();
    await expect(
        page
            .getByText('Browser withdrawal note', { exact: true })
            .filter({ visible: true }),
    ).toBeVisible();

    await page.goto('/temp/seller/support_ticket');
    await page.locator('[data-target="#ticket_modal"]').click();
    const ticket = page.locator('#ticket_modal');
    await expect(ticket.locator('[data-required-legend]')).toHaveCount(1);
    await expect(ticket.locator('#seller-ticket-subject')).toHaveAccessibleName(
        'Subject required',
    );
    await ticket
        .locator('#seller-ticket-subject')
        .fill('Browser seller support');
    await ticket.getByRole('button', { name: 'Send Ticket' }).click();
    expect(
        await ticket
            .locator('#seller-ticket-details')
            .evaluate(
                (input: HTMLTextAreaElement) => input.validity.valueMissing,
            ),
    ).toBe(true);
    await ticket
        .locator('#seller-ticket-details')
        .fill('Please help with my order.');
    await ticket.getByRole('button', { name: 'Send Ticket' }).click();
    await expect(
        page.getByText('Browser seller support', { exact: true }),
    ).toBeVisible();
});

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

test.describe('seller validated shop and commerce forms', () => {
    test.beforeEach(async ({ page }) => {
        seedStoreFixtures(String.raw`
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        Illuminate\Support\Facades\DB::table('shops')->updateOrInsert(['user_id' => $seller->id], ['name' => 'Browser shop', 'slug' => 'browser-shop', 'verification_status' => 1, 'verification_info' => null]);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'verification_form'], ['value' => json_encode([['type' => 'text', 'label' => 'Company'], ['type' => 'file', 'label' => 'Document']])]);
        App\Models\Coupon::query()->where('user_id', $seller->id)->whereIn('code', ['BrowserSellerCoupon', 'BrowserSellerCouponTaken'])->delete();
        App\Models\Coupon::create(['user_id' => $seller->id, 'code' => 'BrowserSellerCouponTaken', 'type' => 'cart_base', 'details' => '{"min_buy":20,"max_discount":10}', 'discount' => 5, 'discount_type' => 'amount', 'start_date' => time(), 'end_date' => time() + 86400]);
    `);
        await loginAsSeller(page);
    });

    test('shop fields match the server and save successfully', async ({
        page,
    }) => {
        await page.goto('/temp/seller/shop');
        const basic = page.locator('form').filter({
            has: page.locator('input[name="section"][value="basic"]'),
        });
        await expect(basic.locator('[data-required-legend]')).toHaveCount(1);
        await expect(basic.locator('#shop-name')).toHaveAccessibleName(
            'Shop Name required',
        );
        await expect(basic.locator('#shop-phone')).not.toHaveAttribute(
            'required',
        );
        await basic.locator('#shop-name').fill('Browser validated shop');
        await basic.locator('#shop-address').fill('Amsterdam');
        await basic.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(
            page.getByText('Your Shop has been updated successfully!', {
                exact: true,
            }),
        ).toBeVisible();
    });

    test('verification requires a document and submits privately', async ({
        page,
    }) => {
        await page.goto('/temp/seller/shop/apply_for_verification');
        await page
            .getByRole('textbox', { name: 'Company required', exact: true })
            .fill('Browser Company');
        await page.locator('#verification-1').setInputFiles({
            name: 'document.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n%%EOF'),
        });
        await page.getByRole('button', { name: 'Apply', exact: true }).click();
        await expect(page).toHaveURL(/\/temp\/seller\/dashboard/);
        await expect(
            page.getByRole('heading', { name: 'Dashboard', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Sales Stat', { exact: true }),
        ).toBeVisible();
        await expect(page.locator('#graph-1')).toBeVisible();
        await expect(
            page.getByText('Top 12 Products', { exact: true }),
        ).toBeVisible();
    });

    test('coupon refusal keeps its fields before a successful save', async ({
        page,
    }) => {
        await page.goto('/temp/seller/coupon/create');
        const coupon = page.locator('form[action$="/seller/coupon"]');
        await expect(coupon.locator('[data-required-legend]')).toHaveCount(1);
        await coupon
            .getByRole('textbox', { name: 'Coupon code required', exact: true })
            .fill('BrowserSellerCouponTaken');
        await coupon
            .getByRole('spinbutton', {
                name: 'Minimum Shopping required',
                exact: true,
            })
            .fill('20');
        await coupon
            .getByRole('spinbutton', {
                name: 'Maximum Discount Amount required',
                exact: true,
            })
            .fill('10');
        await coupon
            .getByRole('spinbutton', { name: 'Discount required', exact: true })
            .fill('5');
        await coupon.locator('#coupon-dates').fill('10/22/2026 - 10/23/2026');
        await coupon.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.locator('#code')).toHaveValue(
            'BrowserSellerCouponTaken',
        );
        await expect(page.locator('.invalid-feedback')).toContainText([
            'Coupon already exist',
        ]);
        await page.locator('#code').fill('BrowserSellerCoupon');
        await page
            .locator('form[action$="/seller/coupon"]')
            .getByRole('button', { name: 'Save', exact: true })
            .click();
        await expect(
            page.getByText('Coupon has been saved successfully.', {
                exact: true,
            }),
        ).toBeVisible();
    });

    test('bulk upload shows an invalid spreadsheet error', async ({ page }) => {
        await page.goto('/temp/seller/product-bulk-upload/index');
        await expect(page.locator('#bulk-file')).toHaveAccessibleName(
            'CSV required',
        );
        await page.locator('#bulk-file').setInputFiles({
            name: 'bad.xlsx',
            mimeType: 'text/plain',
            buffer: Buffer.from('<b>Not a spreadsheet</b>'),
        });
        await page
            .getByRole('button', { name: 'Upload CSV', exact: true })
            .click();
        await expect(page.locator('.invalid-feedback')).toContainText([
            'import file',
        ]);
    });
});

test('seller physical forms preserve old input and persist owner approval price and stock', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        App\Models\Category::firstOrCreate(['slug' => 'browser-physical'], ['name' => 'Browser physical', 'parent_id' => 0, 'level' => 0, 'digital' => 0]);
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        Illuminate\Support\Facades\DB::table('shops')->updateOrInsert(['user_id' => $seller->id], ['name' => 'Browser shop', 'slug' => 'browser-shop']);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'product_approve_by_admin'], ['value' => '1']);
    `);
    await loginAsSeller(page);
    await page.goto('/temp/seller/product/create');
    const form = page.locator('#choice_form');
    await expect(form.locator('[data-required-legend]')).toHaveCount(1);
    await expect(form.locator('[name=name]')).toHaveAccessibleName(
        'Product Name required',
    );
    await form.locator('[name=name]').fill('Browser validated physical');
    await form
        .locator('[name=category_id]')
        .selectOption({ index: 1 }, { force: true });
    await form.locator('[name=unit]').evaluate((node: HTMLInputElement) => {
        node.maxLength = 21;
    });
    await form.locator('[name=unit]').fill('x'.repeat(21));
    await form.locator('[name=unit_price]').fill('12.50');
    await form.locator('[name=discount]').fill('1.50');
    await form.locator('[name=current_stock]').fill('17');
    // Exercise the server boundary, which must protect requests without native validation too.
    await form.evaluate((node: HTMLFormElement) => {
        node.noValidate = true;
    });
    await form
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page.locator('[role=alert]')).toContainText('unit');
    await expect(page.locator('[name=name]')).toHaveValue(
        'Browser validated physical',
    );
    await page.locator('[name=unit]').fill('bottle');
    await page
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/seller\/products$/);
    seedStoreFixtures(String.raw`
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        $product = App\Models\Product::where('name', 'Browser validated physical')->sole();
        if ($product->user_id !== $seller->id || $product->added_by !== 'seller' || $product->approved != 0 || $product->synced_at !== null || $product->unit_price != 12.5 || $product->stocks()->sole()->qty != 17) { throw new RuntimeException('Physical seller persistence failed'); }
    `);
    const edit = page
        .locator('tr')
        .filter({ hasText: 'Browser validated physical' })
        .locator('a[href*="/edit"]');
    await edit.click();
    await expect(page.locator('[name=unit]')).toHaveValue('bottle');
    await page.locator('[name=unit_price]').fill('13.75');
    await page.locator('[name=current_stock]').fill('9');
    await page
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page.locator('[name=unit_price]')).toHaveValue('13.75');
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::where('name', 'Browser validated physical')->sole();
        if ($product->unit_price != 13.75 || $product->stocks()->sole()->qty != 9 || $product->approved != 0) { throw new RuntimeException('Physical seller update failed'); }
    `);
});

test('seller physical variant choices generate rows and retain their prices on edit', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        App\Models\Category::firstOrCreate(['slug' => 'browser-physical'], ['name' => 'Browser physical', 'parent_id' => 0, 'level' => 0, 'digital' => 0]);
        $attribute = App\Models\Attribute::firstOrCreate(['name' => 'Browser Size']);
        foreach (['M', 'L'] as $value) { App\Models\AttributeValue::firstOrCreate(['attribute_id' => $attribute->id, 'value' => $value]); }
    `);
    await loginAsSeller(page);
    await page.goto('/temp/seller/product/create');
    const form = page.locator('#choice_form');
    await form.locator('[name=name]').fill('Browser physical variants');
    await form
        .locator('[name=category_id]')
        .selectOption({ index: 1 }, { force: true });
    await form.locator('[name=unit]').fill('pc');
    await form.locator('[name=unit_price]').fill('12.50');
    await form.locator('[name=discount]').fill('1.50');
    await form
        .locator('#choice_attributes')
        .selectOption({ label: 'Browser Size' }, { force: true });
    const values = form.locator('select[name^=choice_options_]');
    await expect(values).toHaveCount(1);
    await values.selectOption(['M', 'L'], { force: true });
    await expect(form.locator('[name=price_M]')).toBeVisible();
    await form.locator('[name=price_M]').fill('17.50');
    await form.locator('[name=qty_M]').fill('4');
    await form.locator('[name=price_L]').fill('22.50');
    await form.locator('[name=qty_L]').fill('8');
    await form
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/seller\/products$/);
    await page
        .locator('tr')
        .filter({ hasText: 'Browser physical variants' })
        .locator('a[href*="/edit"]')
        .click();
    await expect(page.locator('[name=price_M]')).toHaveValue('17.5');
    await page.locator('[name=price_M]').fill('19.50');
    await page
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page.locator('[name=price_M]')).toHaveValue('19.5');
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::where('name', 'Browser physical variants')->sole();
        if ($product->stocks()->where('variant', 'M')->sole()->price != 19.5 || $product->stocks()->where('variant', 'L')->sole()->qty != 8) { throw new RuntimeException('Variant replacement lost rows'); }
    `);
});

test('seller explicitly reorders legacy attribute choices and preserves the stock record', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        Illuminate\Support\Facades\DB::table('shops')->updateOrInsert(['user_id' => $seller->id], ['name' => 'Browser shop', 'slug' => 'browser-shop']);
        $category = App\Models\Category::firstOrCreate(['slug' => 'browser-repair'], ['name' => 'Browser repair', 'parent_id' => 0, 'digital' => 0]);
        $size = Illuminate\Support\Facades\DB::table('attributes')->insertGetId(['name' => 'Browser repair size']);
        $fabric = Illuminate\Support\Facades\DB::table('attributes')->insertGetId(['name' => 'Browser repair fabric']);
        Illuminate\Support\Facades\DB::table('attribute_values')->insert([['attribute_id' => $size, 'value' => 'M'], ['attribute_id' => $fabric, 'value' => 'Cotton']]);
        $product = App\Models\Product::forceCreate(['name' => 'Browser legacy choice repair', 'slug' => 'browser-legacy-choice-repair', 'category_id' => $category->id, 'user_id' => $seller->id, 'added_by' => 'seller', 'digital' => 0, 'unit' => 'pc', 'unit_price' => '12.50', 'min_qty' => 1, 'discount' => 0, 'discount_type' => 'amount', 'current_stock' => 47, 'stock_visibility_state' => 'quantity', 'colors' => '[]', 'attributes' => json_encode([$size, $fabric]), 'choice_options' => json_encode([['attribute_id' => $size, 'values' => ['M']], ['attribute_id' => $fabric, 'values' => ['Cotton']]])]);
        $product->stocks()->create(['variant' => 'Cotton-M', 'price' => '17.50', 'qty' => 47, 'sku' => 'KEEP']);
    `);
    const productName = 'Browser legacy choice repair';
    const id = storedProduct(productName, '$product->id') as number;
    const expression =
        '$product->stocks()->orderBy("id")->get()->map(fn ($s) => [$s->id, $s->variant, (float) $s->price, (int) $s->qty, $s->sku])->all()';
    const before = storedProduct(productName, expression);
    await loginAsSeller(page);
    await page.goto(`/temp/seller/product/${id}/edit?lang=en`);
    await expect(page.getByRole('alert')).toContainText('Cotton-M');
    await page
        .getByRole('button', {
            name: 'Move attribute up: Browser repair fabric',
            exact: true,
        })
        .click();
    await expect(page.locator('[name="price_Cotton-M"]')).toHaveValue('17.5');
    await expect(page.locator('[name="qty_Cotton-M"]')).toHaveValue('47');
    expect(storedProduct(productName, expression)).toEqual(before);
    await page
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(
        page.getByRole('alert').filter({ hasText: 'Stored stock' }),
    ).toHaveCount(0);
    expect(storedProduct(productName, expression)).toEqual(before);
});

test('seller digital create and edit round trip retains owned file pending approval translations and stock', async ({
    page,
}) => {
    seedStoreFixtures(String.raw`
        $seller = App\Models\StoreUser::where('email', 'browser-seller@example.com')->sole();
        Illuminate\Support\Facades\DB::table('shops')->updateOrInsert(['user_id' => $seller->id], ['name' => 'Browser shop', 'slug' => 'browser-shop']);
        App\Models\Category::firstOrCreate(['slug' => 'browser-digital'], ['name' => 'Browser digital', 'parent_id' => 0, 'digital' => 1]);
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type' => 'product_approve_by_admin'], ['value' => '1']);
    `);
    await loginAsSeller(page);
    await page.goto('/temp/seller/digitalproducts/create');
    let form = page.locator('#digital-product-form');
    await expect(form.locator('[data-required-legend]')).toHaveCount(1);
    await expect(form.locator('[name=name]')).toHaveAccessibleName(
        'Product Name required',
    );
    await form.locator('[name=name]').fill('Browser validated digital');
    await form
        .locator('[name=category_id]')
        .selectOption({ label: 'Browser digital' }, { force: true });
    await form.locator('[name=unit_price]').fill('12.50');
    await form.locator('[name=purchase_price]').fill('3.50');
    await form.locator('[name=discount]').fill('1.50');
    await form
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page.locator('[role=alert]')).toContainText('file');
    await expect(form.locator('[name=name]')).toHaveValue(
        'Browser validated digital',
    );
    await form
        .locator('[data-toggle="aizuploader"]')
        .filter({ has: page.locator('input[name=file]') })
        .click();
    const modal = page.locator('#aizUploaderModal');
    await modal.getByRole('link', { name: 'Upload New' }).click();
    const upload = page.waitForResponse(
        (r) =>
            new URL(r.url()).pathname === '/temp/aiz-uploader/upload' &&
            r.request().method() === 'POST',
    );
    await modal.locator('input[type=file]').setInputFiles({
        name: 'seller-digital-guide.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('A useful digital guide'),
    });
    expect((await upload).status()).toBe(200);
    await modal.getByRole('link', { name: 'Select File', exact: true }).click();
    await modal
        .locator('.aiz-uploader-select[title="seller-digital-guide.txt"]')
        .click();
    await modal.getByRole('button', { name: 'Add Files', exact: true }).click();
    const fileId = await form.locator('[name=file]').inputValue();
    await form
        .getByRole('button', { name: 'Save Product', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/seller\/digitalproducts$/);
    const id = storedProduct(
        'Browser validated digital',
        '$product->id',
    ) as number;
    const saved = storedProduct(
        'Browser validated digital',
        '[$product->added_by, (int) $product->approved, $product->synced_at, (int) $product->file_name, (float) $product->unit_price, (int) $product->stocks()->sole()->qty]',
    );
    expect(saved).toEqual(['seller', 0, null, Number(fileId), 12.5, 0]);
    await page.goto(`/temp/seller/digitalproducts/${id}/edit?lang=en`);
    form = page.locator('#digital-product-form');
    await expect(form.locator('[name=file]')).toHaveValue(fileId);
    await expect(form.locator('[name=unit_price]')).toHaveValue('12.5');
    await form.locator('[name=unit_price]').fill('13.75');
    await form
        .getByRole('button', { name: 'Update Product', exact: true })
        .click();
    await expect(form.locator('[name=unit_price]')).toHaveValue('13.75');
    expect(
        storedProduct(
            'Browser validated digital',
            '[(float) $product->unit_price, (float) $product->stocks()->sole()->price, (int) $product->approved, (int) $product->file_name]',
        ),
    ).toEqual([13.75, 13.75, 0, Number(fileId)]);
});
