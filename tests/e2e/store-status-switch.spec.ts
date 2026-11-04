import { expect, test } from '@playwright/test';
import type { Locator } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { contrastRatio } from './support/contrast';
import { switchLocale } from './support/nav';
import {
    ensureStoreRtlLanguage,
    seedStoreFixtures,
} from './support/storeFixtures';

const NAME = 'Browser switch oil';

/** One in-house product, for sale and active but not featured. */
function seedProduct(): void {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['type'=>'In_House_Products_Section'],['value'=>'1']);
        foreach (App\Models\Product::where('name','${NAME}')->pluck('id') as $id) {
            foreach (['product_translations','product_stocks','product_taxes','wms_product_settings'] as $table) {
                Illuminate\Support\Facades\DB::table($table)->where('product_id',$id)->delete();
            }
            App\Models\Product::whereKey($id)->delete();
        }
        $product = App\Models\Product::forceCreate([
            'name'=>'${NAME}','ar_name'=>'','slug'=>'browser-switch-oil','added_by'=>'admin','digital'=>0,
            'unit_price'=>12.5,'published'=>1,'for_sale'=>1,'featured'=>0,
            'colors'=>'[]','attributes'=>'[]','choice_options'=>'[]','tags'=>'',
        ]);
        App\Models\ProductTranslation::create(['product_id'=>$product->id,'lang'=>'en','name'=>'${NAME}']);
        Illuminate\Support\Facades\DB::table('product_stocks')->insert(['product_id'=>$product->id,'variant'=>'','sku'=>'','price'=>12.5,'qty'=>5]);
    `);
}

/** The knob's distance from the track's physical left and right edges. */
function knobInsets(toggle: Locator): Promise<{ left: number; right: number }> {
    return toggle.evaluate((button) => {
        const track = button
            .querySelector('[data-switch-track]')!
            .getBoundingClientRect();
        const knob = button
            .querySelector('[data-switch-knob]')!
            .getBoundingClientRect();

        return {
            left: knob.left - track.left,
            right: track.right - knob.right,
        };
    });
}

/** The track's color and the first opaque background behind it. */
function trackColors(
    toggle: Locator,
): Promise<{ track: number[]; background: number[] }> {
    return toggle.evaluate((button) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', { willReadFrequently: true })!;
        const rgb = (color: string) => {
            context.clearRect(0, 0, 1, 1);
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);

            return Array.from(context.getImageData(0, 0, 1, 1).data);
        };
        const track = button.querySelector('[data-switch-track]')!;
        let background = rgb('transparent');

        for (
            let node: Element | null = button.parentElement;
            node !== null && background[3] === 0;
            node = node.parentElement
        ) {
            background = rgb(getComputedStyle(node).backgroundColor);
        }

        return {
            track: rgb(getComputedStyle(track).backgroundColor).slice(0, 3),
            background: background.slice(0, 3),
        };
    });
}

test('status switches toggle from the keyboard, mirror in RTL and keep their contrast in dark mode', async ({
    page,
}) => {
    ensureStoreRtlLanguage();
    seedProduct();
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/products/all?search=Browser%20switch');
    const row = page.getByRole('row', { name: new RegExp(NAME) });
    const featured = row.getByRole('switch', { name: 'Featured', exact: true });
    const forSale = row.getByRole('switch', { name: 'For sale', exact: true });
    await expect(featured).toHaveAttribute('aria-checked', 'false');
    await expect(forSale).toHaveAttribute('aria-checked', 'true');

    await featured.focus();
    await page.keyboard.press('Space');
    await expect(featured).toHaveAttribute('aria-checked', 'true');
    await featured.press('Enter');
    await expect(featured).toHaveAttribute('aria-checked', 'false');
    await expect(page).toHaveURL(/search=Browser%20switch$/);

    // Left to right: on sits at the right end, off at the left.
    const onLtr = await knobInsets(forSale);
    const offLtr = await knobInsets(featured);
    expect(onLtr.right).toBeLessThan(onLtr.left);
    expect(offLtr.left).toBeLessThan(offLtr.right);

    await page.emulateMedia({ colorScheme: 'dark' });
    await switchLocale(page, 'Arabic');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    const arabicForSale = row.getByRole('switch', {
        name: 'معروض للبيع',
        exact: true,
    });
    const arabicFeatured = row.getByRole('switch', {
        name: 'مميز',
        exact: true,
    });
    await expect(arabicForSale).toHaveAttribute('aria-checked', 'true');
    await expect(arabicForSale).toContainText('مفعّل');
    await expect(arabicFeatured).toContainText('معطّل');

    // Right to left mirrors it: on sits at the left end.
    const onRtl = await knobInsets(arabicForSale);
    const offRtl = await knobInsets(arabicFeatured);
    expect(onRtl.left).toBeLessThan(onRtl.right);
    expect(offRtl.right).toBeLessThan(offRtl.left);

    for (const toggle of [arabicForSale, arabicFeatured]) {
        const colors = await trackColors(toggle);
        expect(Math.max(...colors.background)).toBeLessThan(60);
        expect(
            contrastRatio(colors.track, colors.background),
        ).toBeGreaterThanOrEqual(3);
    }
});
