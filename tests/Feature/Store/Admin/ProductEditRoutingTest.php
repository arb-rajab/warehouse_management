<?php

use App\Models\Category;
use App\Models\Language;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
});

test('edit aliases keep physical forms and redirect digital products to the working prefixed named route', function (string $entry, string $list, bool $digital, ?string $lang) {
    actingAsStoreUser(createStoreStaff('product_edit', 'edit_digital_product'));
    $category = Category::forceCreate(['name' => 'Catalogue', 'slug' => 'catalogue', 'parent_id' => 0, 'digital' => $digital ? 1 : 0]);
    $product = createStoreProduct(['category_id' => $category->id, 'digital' => $digital ? 1 : 0, 'thumbnail_img' => null]);
    $parameters = ['product' => $product->id, 'lang' => $lang];

    $response = $this->get(route($entry, $parameters));
    if ($digital) {
        $target = route('digitalproducts.edit', $parameters);
        expect(parse_url($target, PHP_URL_PATH))->toBe('/temp/admin/digitalproducts/edit/'.$product->id);
        $response->assertRedirect($target);
        $this->get($target)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Product/DigitalProducts/Edit'));
    } else {
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Product/Products/Edit')->where('listType', $list)->where('lang', $lang ?? 'en'));
    }
})->with([
    'admin alias' => ['products.admin.edit', 'admin'],
    'seller alias' => ['products.seller.edit', 'seller'],
])->with(['physical' => false, 'digital' => true])->with(['default' => null, 'Arabic' => 'ar']);
