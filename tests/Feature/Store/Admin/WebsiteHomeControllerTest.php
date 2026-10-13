<?php

use App\Http\Requests\Store\Admin\UpdateWebsiteHomeRequest;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\Upload;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function websiteHomePayload(array $overrides = []): array
{
    return array_replace(['home_slider_images' => [], 'home_slider_links' => [], 'cupon_background_color' => '', 'cupon_title' => '', 'cupon_subtitle' => '', 'home_categories' => []], $overrides);
}

beforeEach(function () {
    Page::forceCreate(['title' => 'Home', 'slug' => 'home', 'type' => 'home_page', 'content' => '<p>Keep content</p>']);
});

test('home settings use the existing edit URL and send only live settings and narrow translated categories', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $category = Category::forceCreate(['name' => 'Food', 'slug' => 'food', 'parent_id' => 0]);
    CategoryTranslation::forceCreate(['category_id' => $category->id, 'lang' => 'en', 'name' => 'Translated food']);
    BusinessSetting::forceCreate(['type' => 'coupon_system', 'value' => '1']);
    BusinessSetting::forceCreate(['type' => 'header_script', 'value' => 'secret']);
    $this->get('/temp/admin/website/custom-pages/edit/home?page=home')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Home')->where('settings', websiteHomePayload())
        ->has('settings', count(UpdateWebsiteHomeRequest::KEYS))->where('couponEnabled', true)
        ->has('categories', 1)->where('categories.0', ['id' => $category->id, 'name' => 'Translated food']));
});

test('home saves only its keys and preserves their public JSON and misspelt coupon contracts', function () {
    $staff = actingAsStoreUser(createStoreStaff('edit_website_page'));
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $category = Category::forceCreate(['name' => 'Food', 'slug' => 'food', 'parent_id' => 0]);
    $noise = BusinessSetting::forceCreate(['type' => 'header_logo', 'value' => 'unchanged']);
    $payload = websiteHomePayload(['home_slider_images' => [(string) $image->id], 'home_slider_links' => ['/offers'], 'cupon_background_color' => '#abcdef', 'cupon_title' => 'Coupon', 'cupon_subtitle' => 'Save', 'home_categories' => [(string) $category->id]]);
    $this->put(route('website.home.update'), ['settings' => $payload])->assertRedirect(route('website.home.edit'));
    expect(json_decode(get_setting('home_slider_images'), true))->toBe($payload['home_slider_images'])
        ->and(json_decode(get_setting('home_slider_links'), true))->toBe(['/offers'])
        ->and(json_decode(get_setting('home_categories'), true))->toBe($payload['home_categories'])
        ->and(get_setting('cupon_title'))->toBe('Coupon')->and(get_setting('cupon_background_color'))->toBe('#abcdef')
        ->and($noise->fresh()->value)->toBe('unchanged')->and(Page::sole()->content)->toBe('<p>Keep content</p>');
    seedStoreCurrency();
    $this->get('/temp')->assertOk()->assertViewIs('shop.frontend.index')->assertSee('href="/offers"', false)->assertSee(uploaded_asset($image->id), false);
});

test('empty slider slots remain paired after a save and reload', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->put(route('website.home.update'), ['settings' => websiteHomePayload(['home_slider_images' => [''], 'home_slider_links' => ['']])])->assertRedirect();
    $this->get(route('website.home.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings.home_slider_images', [''])->where('settings.home_slider_links', ['']));
});

test('a header editor cannot read or write home settings', function () {
    actingAsStoreUser(createStoreStaff('header_setup'));
    $this->get(route('website.home.edit'))->assertForbidden();
    $this->put(route('website.home.update'), ['settings' => websiteHomePayload()])->assertForbidden();
    expect(BusinessSetting::count())->toBe(0);
});

test('home settings reject other pages, scripts, removed addons and commented settings atomically', function (string $key) {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->put(route('website.home.update'), ['settings' => websiteHomePayload([$key => 'forged'])])->assertSessionHasErrors('settings');
    expect(BusinessSetting::count())->toBe(0);
})->with(['header_logo', 'footer_text', 'header_script', 'footer_script', 'auction_banner_image', 'home_banner1_images', 'top10_brands']);

test('legacy settings cannot bypass the home allowlist endpoint', function (string $key) {
    actingAsStoreUser(createStoreStaff('currency_setup'));
    $this->post(route('business_settings.update'), ['types' => [$key], $key => 'forged'])->assertForbidden();
    expect(BusinessSetting::count())->toBe(0);
})->with(UpdateWebsiteHomeRequest::KEYS);

test('home settings validate references and paired arrays before writing', function (string $key, mixed $value) {
    config(['waf.enabled' => false]);
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $payload = websiteHomePayload(['home_slider_images' => [''], 'home_slider_links' => ['/offers']]);
    $this->put(route('website.home.update'), ['settings' => array_replace($payload, [$key => $value])])->assertSessionHasErrors();
    expect(BusinessSetting::count())->toBe(0);
})->with([
    'unpaired links' => ['home_slider_links', []], 'script link' => ['home_slider_links', ['javascript:alert(1)']],
    'missing image' => ['home_slider_images', ['999999']], 'CSV IDs' => ['home_slider_images', ['1,2']],
    'missing category' => ['home_categories', ['999999']], 'invalid color' => ['cupon_background_color', 'url(javascript:x)'],
    'long title' => ['cupon_title', str_repeat('a', 256)], 'too many rows' => ['home_categories', array_fill(0, 51, 1)],
]);

test('missing home page settings read and update are 404 without writes', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    Page::query()->delete();
    $this->get(route('website.home.edit'))->assertNotFound();
    $this->put(route('website.home.update'), ['settings' => websiteHomePayload()])->assertNotFound();
    expect(BusinessSetting::count())->toBe(0);
});

test('home settings refuse unauthorized store callers without writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.home.edit')), $caller);
    assertStoreCallerRefused($this->put(route('website.home.update'), ['settings' => websiteHomePayload()]), $caller);
    expect(BusinessSetting::count())->toBe(0);
})->with('refused store admin callers');

test('WMS sessions cannot read or write home settings', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('website.home.edit'))->assertRedirect(route('shop.login'));
    $this->put(route('website.home.update'), ['settings' => websiteHomePayload()])->assertRedirect(route('shop.login'));
    expect(BusinessSetting::count())->toBe(0);
});
