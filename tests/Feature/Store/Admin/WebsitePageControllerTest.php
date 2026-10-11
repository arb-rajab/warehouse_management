<?php

use App\Http\Controllers\PageController;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function adminWebsitePage(array $overrides = []): Page
{
    return Page::forceCreate(array_replace(['title' => 'About', 'slug' => 'about-'.Page::count(), 'type' => 'custom_page', 'content' => '<p>Public content</p>', 'meta_description' => 'Private to editor'], $overrides));
}

test('the website list sends translated titles and public URLs without content or metadata', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    $record = adminWebsitePage(['slug' => 'about']);
    PageTranslation::forceCreate(['page_id' => $record->id, 'lang' => 'en', 'title' => 'Translated about', 'content' => 'Not a list prop']);
    $this->get(route('website.pages'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Pages/Index')->where('filters', ['per_page' => 20])
        ->where('defaultLanguage', config('app.default_language'))->has('pages.data', 1)
        ->where('pages.data.0', ['id' => $record->id, 'title' => 'Translated about', 'slug' => 'about', 'type' => 'custom_page', 'public_url' => route('custom-pages.show_custom_page', 'about')]));
    $view = app(PageController::class)->show_custom_page('about');
    expect($view->name())->toBe('shop.frontend.custom_page')
        ->and($view->getData()['page']->getTranslation('content'))->toBe('Not a list prop');
    $this->get(route('mobile.custom-pages', 'about'))->assertOk()->assertViewIs('shop.frontend.m_custom_page')->assertSee('Not a list prop');
});

test('website pages paginate with an allowed and a refused page size', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    collect(range(1, 23))->each(fn () => adminWebsitePage());
    $this->get(route('website.pages'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('pages.data', 20)->where('pages.meta.total', 23));
    $this->get(route('website.pages', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('pages.data', 3));
    $this->get(route('website.pages', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('pages.data', 10)->where('filters.per_page', 10));
    $this->get(route('website.pages', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('a homepage links to the public homepage rather than the custom page renderer', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    adminWebsitePage(['slug' => 'home', 'type' => 'home_page']);
    $this->get(route('website.pages'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('pages.data.0.public_url', route('home')));
});

test('an exact delete permission removes only a custom page and its translations', function () {
    actingAsStoreUser(createStoreStaff('delete_website_page'));
    $page = adminWebsitePage();
    $noise = adminWebsitePage();
    PageTranslation::forceCreate(['page_id' => $page->id, 'lang' => 'en', 'title' => 'Gone', 'content' => 'Gone content']);
    $kept = PageTranslation::forceCreate(['page_id' => $noise->id, 'lang' => 'en', 'title' => 'Keep', 'content' => 'Keep content']);
    $this->delete(route('custom-pages.destroy', $page).'?page=2&per_page=20')->assertRedirect(route('website.pages', ['page' => 2, 'per_page' => 20]));
    $this->assertModelMissing($page);
    $this->assertModelExists($noise);
    expect(PageTranslation::pluck('id')->all())->toBe([$kept->id]);
});

test('built-in pages cannot be deleted even by a super admin', function (string $type) {
    actingAsStoreUser(createStoreAdmin());
    $page = adminWebsitePage(['type' => $type]);
    $this->delete(route('custom-pages.destroy', $page))->assertForbidden();
    $this->assertModelExists($page);
})->with(['home_page', 'about_page']);

test('a missing page delete is a 404', function () {
    actingAsStoreUser(createStoreStaff('delete_website_page'));
    $this->delete(route('custom-pages.destroy', 999999))->assertNotFound();
});

test('a read-only page viewer cannot delete by URL', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    $page = adminWebsitePage();
    $this->delete(route('custom-pages.destroy', $page))->assertForbidden();
    $this->assertModelExists($page);
});

test('website lists and deletes refuse unauthorized callers without writes', function (string $caller) {
    $page = adminWebsitePage();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.pages')), $caller);
    assertStoreCallerRefused($this->delete(route('custom-pages.destroy', $page)), $caller);
    $this->assertModelExists($page);
})->with('refused store admin callers');

test('WMS sessions cannot read or delete website pages', function () {
    $page = adminWebsitePage();
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('website.pages'))->assertRedirect(route('shop.login'));
    $this->delete(route('custom-pages.destroy', $page))->assertRedirect(route('shop.login'));
    $this->assertModelExists($page);
});
