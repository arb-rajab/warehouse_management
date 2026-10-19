import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

const NAMES = [
    'Browser olive oil',
    'Browser kept oil',
    'Browser bulk oil A',
    'Browser bulk oil B',
    'Browser seller oil',
];

/** Four in-house products and one seller product, fresh for each test. */
function seedProducts(): void {
    seedStoreFixtures(String.raw`
        foreach (['In_House_Products_Section', 'vendor_system_activation'] as $type) {
            Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type'=>$type],['value'=>'1']);
        }
        foreach (${JSON.stringify(NAMES).replace(/"/g, "'")} as $name) {
            foreach (App\Models\Product::where('name','like',$name.'%')->pluck('id') as $id) {
                foreach (['product_translations','product_stocks','product_taxes','wms_product_settings'] as $table) {
                    Illuminate\Support\Facades\DB::table($table)->where('product_id',$id)->delete();
                }
                App\Models\Product::whereKey($id)->delete();
            }
        }
        foreach ([
            ['Browser olive oil','admin','2026-04-01'], ['Browser kept oil','admin','2026-03-01'],
            ['Browser bulk oil A','admin','2026-02-01'], ['Browser bulk oil B','admin','2026-01-01'],
            ['Browser seller oil','seller','2026-05-01'],
        ] as [$name,$addedBy,$created]) {
            $seller = $addedBy === 'seller';
            $product = App\Models\Product::forceCreate([
                'name'=>$name,'ar_name'=>'','slug'=>Illuminate\Support\Str::slug($name),'added_by'=>$addedBy,'digital'=>0,
                'unit_price'=>12.5,'published'=>$seller ? 0 : 1,'for_sale'=>$seller ? 0 : 1,'featured'=>0,'mat_id'=>'BR'.strlen($name),
                'colors'=>'[]','attributes'=>'[]','choice_options'=>'[]','tags'=>'','created_at'=>$created,
            ]);
            App\Models\ProductTranslation::create(['product_id'=>$product->id,'lang'=>'en','name'=>$name]);
            Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id'=>$product->id,'variant'=>'','sku'=>'','price'=>12.5,'qty'=>5]);
        }
    `);
}

function row(page: Page, name: string): Locator {
    return page.getByRole('row', { name: new RegExp(`${name}\\b`) });
}

test('product list switches, native single and bulk delete confirmations, an Inertia visit into the edit form and an Inertia visit into the seller list', async ({
    page,
}) => {
    seedProducts();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/products/all?search=Browser');
    await expect(
        page.getByRole('heading', { name: 'All products', exact: true }),
    ).toBeVisible();
    const olive = row(page, 'Browser olive oil');
    const kept = row(page, 'Browser kept oil');
    await expect(row(page, 'Browser seller oil')).toBeVisible();

    await olive
        .getByRole('button', { name: 'Enable Featured', exact: true })
        .click();
    await expect(
        olive.getByRole('button', { name: 'Disable Featured', exact: true }),
    ).toBeVisible();
    await olive
        .getByRole('button', { name: 'Disable For sale', exact: true })
        .click();
    await expect(
        olive.getByRole('button', { name: 'Enable For sale', exact: true }),
    ).toBeVisible();
    await expect(
        olive.getByRole('button', { name: 'Enable Featured', exact: true }),
    ).toBeVisible();
    await olive
        .getByRole('button', { name: 'Disable Active', exact: true })
        .click();
    await expect(
        olive.getByRole('button', { name: 'Enable Active', exact: true }),
    ).toBeVisible();
    await expect(page).toHaveURL(
        /\/temp\/admin\/products\/all\?search=Browser$/,
    );
    await expect(
        kept.getByRole('button', { name: 'Disable Active', exact: true }),
    ).toBeVisible();
    seedStoreFixtures(String.raw`
        $product = App\Models\Product::where('name','Browser olive oil')->firstOrFail();
        if ($product->published || $product->for_sale != 0 || $product->featured != 0) {throw new RuntimeException('Switch writes were not persisted');}
        if (Illuminate\Support\Facades\DB::table('product_stocks')->where('product_id',$product->id)->value('qty') != 0) {throw new RuntimeException('Switching off kept the stock');}
        $kept = App\Models\Product::where('name','Browser kept oil')->firstOrFail();
        if (!$kept->published || $kept->for_sale != 1) {throw new RuntimeException('Another product changed');}
    `);

    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe(
            'Delete Browser olive oil? This cannot be undone.',
        );
        void dialog.dismiss();
    });
    await olive.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(olive).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await olive.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(olive).toHaveCount(0);
    await expect(kept).toBeVisible();

    await row(page, 'Browser bulk oil A')
        .getByRole('checkbox', { name: 'Select Browser bulk oil A' })
        .check();
    await row(page, 'Browser bulk oil B')
        .getByRole('checkbox', { name: 'Select Browser bulk oil B' })
        .check();
    page.once('dialog', (dialog) => {
        expect(dialog.message()).toBe(
            'Delete 2 selected products? This cannot be undone.',
        );
        void dialog.dismiss();
    });
    await page
        .getByRole('button', { name: 'Delete selection', exact: true })
        .click();
    await expect(row(page, 'Browser bulk oil A')).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('button', { name: 'Delete selection', exact: true })
        .click();
    await expect(row(page, 'Browser bulk oil A')).toHaveCount(0);
    await expect(row(page, 'Browser bulk oil B')).toHaveCount(0);
    await expect(kept).toBeVisible();
    seedStoreFixtures(String.raw`
        foreach (['Browser olive oil','Browser bulk oil A','Browser bulk oil B'] as $name) {
            if (App\Models\Product::where('name',$name)->exists()) {throw new RuntimeException($name.' was not deleted');}
        }
        foreach (['Browser kept oil','Browser seller oil'] as $name) {
            if (!App\Models\Product::where('name',$name)->exists()) {throw new RuntimeException($name.' was deleted');}
        }
    `);

    const editRequest = page.waitForRequest((request) =>
        /\/temp\/admin\/products\/admin\/\d+\/edit/.test(request.url()),
    );
    await kept.getByRole('link', { name: 'Edit', exact: true }).click();
    // The physical product edit form is an Inertia page now, so the link
    // visits it without a full load.
    expect((await editRequest).headers()['x-inertia']).toBe('true');
    await expect(page).toHaveURL(
        /\/temp\/admin\/products\/admin\/\d+\/edit\?lang=en$/,
    );
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Edit product', exact: true }),
    ).toBeVisible();

    await page.goto('/temp/admin/products/all?search=Browser');
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

    const sellerRequest = page.waitForRequest((request) =>
        request.url().endsWith('/temp/admin/products/seller'),
    );
    await navigation
        .getByRole('link', { name: 'Seller products', exact: true })
        .click();
    expect((await sellerRequest).headers()['x-inertia']).toBe('true');
    await expect(page.locator('#app')).toHaveCount(1);
    await expect(page.getByText('Browser seller oil')).toBeVisible();
    await expect(page.getByText('Browser kept oil')).toHaveCount(0);
});

test('product lists keep RTL and dark contrast and open from the sidebar as Inertia visits', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedProducts();
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

    for (const [label, path, heading] of [
        [
            'In-house products',
            '/temp/admin/products/admin',
            'In-house products',
        ],
        ['All products', '/temp/admin/products/all', 'All products'],
    ] as const) {
        const response = page.waitForResponse(
            (response) =>
                response.url().endsWith(path) &&
                response.request().headers()['x-inertia'] === 'true',
        );
        await navigation
            .getByRole('link', { name: label, exact: true })
            .click();
        expect((await response).ok()).toBe(true);
        await expect(
            page.getByRole('heading', { name: heading, exact: true }),
        ).toBeVisible();
    }

    await page.goto('/temp/admin/products/all?search=Browser');
    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(
        page.getByRole('heading', { name: 'جميع المنتجات', exact: true }),
    ).toBeVisible();
    const olive = row(page, 'Browser olive oil');
    await expect(
        olive.getByRole('button', { name: 'تعطيل نشط', exact: true }),
    ).toBeVisible();
    await expect(
        olive.getByRole('link', { name: 'تعديل', exact: true }),
    ).toBeVisible();

    for (const target of [
        olive.getByText('Browser olive oil', { exact: true }),
        page.getByRole('columnheader', { name: 'رقم المادة', exact: true }),
        olive.getByRole('cell', { name: 'BR17', exact: true }),
        page.locator('#product-presets').getByRole('link', {
            name: 'جميع النشطة',
            exact: true,
        }),
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
        page.getByRole('heading', { name: 'All products', exact: true }),
    ).toBeVisible();
});
