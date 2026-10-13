import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

/** Two digital products and a physical one, fresh for each test. */
function seedDigitalProducts(): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type'=>'Digital_Products_Section'],['value'=>'1']);
        foreach (['Browser e-book','Browser kept e-book','Browser physical'] as $name) {
            foreach (App\Models\Product::where('name',$name)->pluck('id') as $id) {
                Illuminate\Support\Facades\DB::table('product_translations')->where('product_id',$id)->delete();
                Illuminate\Support\Facades\DB::table('product_stocks')->where('product_id',$id)->delete();
                Illuminate\Support\Facades\DB::table('wms_product_settings')->where('product_id',$id)->delete();
                App\Models\Product::whereKey($id)->delete();
            }
        }
        foreach ([['Browser kept e-book',1,'2026-01-01'],['Browser e-book',1,'2026-02-01'],['Browser physical',0,'2026-03-01']] as [$name,$digital,$created]) {
            $product = App\Models\Product::forceCreate(['name'=>$name,'ar_name'=>'','slug'=>Illuminate\Support\Str::slug($name),'digital'=>$digital,'added_by'=>'admin','unit_price'=>12.5,'published'=>1,'featured'=>0,'todays_deal'=>0,'created_at'=>$created]);
            App\Models\ProductTranslation::create(['product_id'=>$product->id,'lang'=>'en','name'=>$name]);
            Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id'=>$product->id,'variant'=>'','sku'=>'','price'=>12.5,'qty'=>5]);
        }
    `);
}

test('digital product list switches, native delete confirmation and Inertia visits into the create and edit forms', async ({
    page,
}) => {
    seedDigitalProducts();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/digitalproducts');
    await expect(
        page.getByRole('heading', { name: 'Digital products', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('row', { name: /Browser physical/ }),
    ).toHaveCount(0);
    const row = page.getByRole('row', { name: /Browser e-book/ });
    const kept = page.getByRole('row', { name: /Browser kept e-book/ });

    await row
        .getByRole('button', { name: "Enable Today's deal", exact: true })
        .click();
    await expect(
        row.getByRole('button', { name: "Disable Today's deal", exact: true }),
    ).toBeVisible();
    await row
        .getByRole('button', { name: 'Enable Featured', exact: true })
        .click();
    await expect(
        row.getByRole('button', { name: 'Disable Featured', exact: true }),
    ).toBeVisible();
    await row
        .getByRole('button', { name: 'Disable Published', exact: true })
        .click();
    await expect(
        row.getByRole('button', { name: 'Enable Published', exact: true }),
    ).toBeVisible();
    await expect(
        row.getByRole('button', { name: 'Enable Featured', exact: true }),
    ).toBeVisible();
    await expect(
        kept.getByRole('button', { name: 'Disable Published', exact: true }),
    ).toBeVisible();
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::where('name','Browser e-book')->firstOrFail();
        if ($product->published || $product->featured != 0 || $product->todays_deal != 1) {throw new RuntimeException('Switch writes were not persisted');}
        if (Illuminate\Support\Facades\DB::table('product_stocks')->where('product_id',$product->id)->value('qty') != 0) {throw new RuntimeException('Unpublishing kept the stock');}
        $kept = App\Models\Product::where('name','Browser kept e-book')->firstOrFail();
        if (!$kept->published || $kept->todays_deal != 0) {throw new RuntimeException('Another product changed');}
    `);

    const editVisit = page.waitForResponse(
        (response) =>
            /^\/temp\/admin\/digitalproducts\/edit\/\d+$/.test(
                new URL(response.url()).pathname,
            ) && response.request().headers()['x-inertia'] === 'true',
    );
    await row.getByRole('link', { name: 'Edit', exact: true }).click();
    expect((await editVisit).status()).toBe(200);
    await expect(page).toHaveURL(
        /\/temp\/admin\/digitalproducts\/edit\/\d+\?lang=en$/,
    );
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Edit digital product' }),
    ).toBeVisible();
    await page.goto('/temp/admin/digitalproducts');
    const createVisit = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/digitalproducts/create') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await page
        .getByRole('link', { name: 'Add digital product', exact: true })
        .click();
    expect((await createVisit).status()).toBe(200);
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Add new digital product' }),
    ).toBeVisible();

    await page.goto('/temp/admin/digitalproducts');
    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe(
            'Delete Browser e-book? This cannot be undone.',
        );
        void dialog.dismiss();
    });
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toHaveCount(0);
    await expect(kept).toBeVisible();
    seedStoreFixtures(String.raw`
        if (App\Models\Product::where('name','Browser e-book')->exists()) {throw new RuntimeException('Digital product was not deleted');}
        if (!App\Models\Product::where('name','Browser kept e-book')->exists()) {throw new RuntimeException('Another digital product was deleted');}
    `);
});

test('digital product list keeps RTL and dark contrast and opens from the sidebar as an Inertia visit', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedDigitalProducts();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/staffs');
    const navigation = page.getByRole('navigation', {
        name: 'Store admin menu',
        exact: true,
    });
    const products = navigation.getByRole('button', {
        name: 'Products',
        exact: true,
    });

    if ((await products.getAttribute('aria-expanded')) !== 'true') {
        await products.click();
    }

    const response = page.waitForResponse(
        (response) =>
            response.url().endsWith('/temp/admin/digitalproducts') &&
            response.request().headers()['x-inertia'] === 'true',
    );
    await navigation
        .getByRole('link', { name: 'Digital products', exact: true })
        .click();
    expect((await response).ok()).toBe(true);
    await expect(
        page.getByRole('heading', { name: 'Digital products', exact: true }),
    ).toBeVisible();

    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'المنتجات الرقمية', exact: true }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: /Browser e-book/ });
    await expect(
        row.getByRole('button', { name: 'تعطيل منشور', exact: true }),
    ).toBeVisible();
    await expect(
        row.getByRole('link', { name: 'تعديل', exact: true }),
    ).toBeVisible();

    for (const target of [
        row.getByRole('link', { name: 'Browser e-book' }),
        page.getByRole('columnheader', { name: 'السعر الأساسي', exact: true }),
        row.getByRole('cell', { name: '12.50', exact: true }),
    ]) {
        const colors = await target.evaluate((element) => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            })!;
            const rgb = (color: string) => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);

                return Array.from(context.getImageData(0, 0, 1, 1).data) as [
                    number,
                    number,
                    number,
                    number,
                ];
            };
            let background = rgb('transparent');

            for (
                let node: Element | null = element;
                node !== null && background[3] === 0;
                node = node.parentElement
            ) {
                background = rgb(getComputedStyle(node).backgroundColor);
            }

            return {
                foreground: rgb(getComputedStyle(element).color).slice(0, 3),
                background: background.slice(0, 3),
            };
        });
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(
                colors.foreground as [number, number, number],
                colors.background as [number, number, number],
            ),
        ).toBeGreaterThanOrEqual(4.5);
    }

    await switchLocale(page, 'الإنجليزية');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(
        page.getByRole('heading', { name: 'Digital products', exact: true }),
    ).toBeVisible();
});
