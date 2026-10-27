import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { seedStoreFixtures, storedProduct } from './support/storeFixtures';

test('customer and campaign links open the same slugless product in the storefront', async ({
    page,
    baseURL,
}) => {
    seedStoreFixtures(String.raw`
        $customer = new App\Models\StoreUser;
        $customer->forceFill(['email' => 'd15-browser@example.test', 'name' => 'D15 Browser', 'user_type' => 'customer', 'email_verified_at' => now(), 'admin_verified' => 1, 'banned' => 0]);
        $customer->password = 'Password123!';
        $customer->save();
        $owner = App\Models\StoreUser::where('email', 'store-admin@example.com')->sole();
        $product = App\Models\Product::factory()->create(['name' => 'D15 browser download', 'ar_name' => 'D15 browser Arabic', 'serial' => 'd15-browser', 'slug' => null, 'digital' => 1, 'user_id' => $owner->id, 'added_by' => 'admin', 'approved' => 1, 'published' => 1, 'auction_product' => 0, 'category_id' => null, 'brand_id' => null, 'thumbnail_img' => null, 'unit_price' => 5, 'discount' => 0]);
        App\Models\ProductStock::forceCreate(['product_id' => $product->id, 'variant' => '', 'sku' => 'D15', 'price' => 5, 'qty' => 10]);
        App\Models\Wishlist::forceCreate(['user_id' => $customer->id, 'product_id' => $product->id]);
        $order = App\Models\Order::forceCreate(['user_id' => $customer->id, 'shipping_address' => '{}', 'shipping_type' => 'home_delivery', 'payment_type' => 'cash_on_delivery', 'date' => now()->timestamp, 'code' => 'D15-BROWSER', 'payment_status' => 'paid', 'grand_total' => 5]);
        App\Models\OrderDetail::forceCreate(['order_id' => $order->id, 'seller_id' => $owner->id, 'product_id' => $product->id, 'product_serial' => $product->serial, 'quantity' => 1, 'price' => 5, 'tax' => 0, 'payment_status' => 'paid']);
        $deal = App\Models\FlashDeal::forceCreate(['title' => 'D15 browser campaign', 'slug' => 'd15-browser-campaign', 'status' => 1, 'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addDay()->timestamp, 'text_color' => 'dark']);
        App\Models\FlashDealProduct::forceCreate(['flash_deal_id' => $deal->id, 'product_id' => $product->id]);
        App\Models\Coupon::forceCreate(['user_id' => $owner->id, 'type' => 'product_base', 'code' => 'D15-BROWSER', 'discount' => 1, 'discount_type' => 'amount', 'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addDay()->timestamp, 'details' => json_encode([['product_id' => $product->id]])]);
        // Multiple active coupons exercise the listing's eager-loading contract even in an isolated run.
        App\Models\Coupon::forceCreate(['user_id' => $owner->id, 'type' => 'cart_base', 'code' => 'D15-BROWSER-CART', 'discount' => 1, 'discount_type' => 'amount', 'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addDay()->timestamp, 'details' => json_encode(['min_buy' => 5, 'max_discount' => 1])]);
    `);
    const id = Number(storedProduct('D15 browser download', '$product->id'));
    const destination = `${baseURL}/temp/product/~id-${id}?catalog_id=${id}`;
    await page.goto('/temp/login');
    const login = page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: 'Login' }) });
    await login.locator('#email').fill('d15-browser@example.test');
    await login.locator('#password').fill('Password123!');
    await login.getByRole('button', { name: 'Login' }).click();
    await expect(page).toHaveURL(/\/temp\/dashboard/);

    for (const path of [
        '/temp/dashboard',
        '/temp/digital-purchase-history',
        '/temp/flash-deal/d15-browser-campaign',
        '/temp/coupons',
    ]) {
        const response = await page.goto(path);
        expect(response?.status(), `GET ${path}`).toBe(200);
        const link = page.locator(`a[href="${destination}"]`).first();
        await expect(link).toHaveAttribute('href', destination);
        let productPage = page;

        if ((await link.getAttribute('target')) === '_blank') {
            const popup = page.waitForEvent('popup');
            await link.click();
            productPage = await popup;
        } else {
            await link.click();
        }

        await expect(productPage).toHaveURL(destination);
        await expect(
            productPage.getByRole('heading', {
                name: 'D15 browser download | D15 browser Arabic',
                exact: true,
            }),
        ).toBeVisible();

        if (productPage !== page) {
            await productPage.close();
        }
    }
});

test('customer views follow the server URL for null empty normal and zero product slugs', async ({
    page,
    baseURL,
}) => {
    seedStoreFixtures(String.raw`
        $customer = new App\Models\StoreUser;
        $customer->forceFill(['name' => 'D15 Views Browser', 'email' => 'd15-views@example.test', 'user_type' => 'customer', 'email_verified_at' => now(), 'banned' => 0])->save();
        foreach ([null, '', 'd15-views-normal', '0'] as $index => $slug) {
            $product = App\Models\Product::factory()->create(['name' => 'D15 viewed '.$index, 'ar_name' => 'Viewed Arabic', 'slug' => $slug, 'approved' => 1, 'auction_product' => 0, 'category_id' => null, 'brand_id' => null]);
            Illuminate\Support\Facades\DB::table('user_page_views')->insert(['user_id' => $customer->id, 'viewable_type' => App\Models\Product::class, 'viewable_id' => $product->id, 'viewed_at' => now()]);
        }
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/customers?search=d15-views@example.test');
    const viewsLink = page
        .getByRole('row')
        .filter({ hasText: 'd15-views@example.test' })
        .getByRole('link', { name: 'Page views', exact: true });
    const viewsUrl = await viewsLink.getAttribute('href');
    expect(viewsUrl).toBeTruthy();
    await viewsLink.click();

    for (const index of [0, 1, 2, 3]) {
        const id = Number(storedProduct(`D15 viewed ${index}`, '$product->id'));
        const destination =
            index < 2
                ? `/temp/product/~id-${id}?catalog_id=${id}`
                : `/temp/product/${index === 2 ? 'd15-views-normal' : '0'}`;
        const link = page.getByRole('link', {
            name: `D15 viewed ${index}`,
            exact: true,
        });
        await expect(link).toHaveAttribute('href', destination);
        await link.click();
        await expect(page).toHaveURL(`${baseURL}${destination}`);
        await expect(
            page.getByRole('heading', {
                name: `D15 viewed ${index} | Viewed Arabic`,
                exact: true,
            }),
        ).toBeVisible();
        await page.goto(viewsUrl!);
    }
});
