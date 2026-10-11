<?php

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Upload;
use Inertia\Testing\AssertableInertia as Assert;

function cmsPayload(array $overrides = []): array
{
    return array_replace(['title' => 'About', 'slug' => 'about', 'content' => '<h2>About</h2><script>attack</script>', 'meta_title' => 'About meta', 'meta_description' => 'Meta description', 'keywords' => 'store,about', 'meta_image' => null, 'lang' => 'en'], $overrides);
}
function cmsPage(array $overrides = []): Page
{
    return Page::forceCreate(array_replace(['title' => 'Old', 'slug' => 'old', 'content' => 'Old content', 'type' => 'custom_page'], $overrides));
}
test('custom page create requires its exact permission and renders narrow form props', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    $this->get(route('custom-pages.create'))->assertForbidden();
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $this->get(route('custom-pages.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Website/Pages/Create')->where('lang', 'en')->has('languages'));
    $this->post(route('custom-pages.store'), cmsPayload() + ['type' => 'home_page', 'user_id' => 999])->assertRedirect(route('website.pages'));
    $page = Page::first();
    expect($page->type)->toBe('custom_page')->and($page->content)->toBe('<h2>About</h2>')->and($page->page_translations()->first()->content)->toBe('<h2>About</h2>');
});
test('page updates translate one language without replacing default content or built-in slugs', function () {
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic']);
    $page = cmsPage(['slug' => 'about', 'type' => 'about_page']);
    $noise = cmsPage();
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->put(route('custom-pages.update', $page), cmsPayload(['slug' => 'about', 'lang' => 'ar', 'title' => 'عربي', 'content' => '<p>عربي</p>']))->assertRedirect(route('website.pages'));
    expect($page->fresh()->title)->toBe('Old')->and($page->fresh()->content)->toBe('Old content')->and($page->page_translations()->where('lang', 'ar')->value('content'))->toBe('<p>عربي</p>')->and($noise->fresh()->content)->toBe('Old content');
    $this->get(route('custom-pages.edit', ['page' => $page->id, 'lang' => 'ar']))->assertInertia(fn (Assert $result) => $result->component('Store/Admin/Website/Pages/Edit')->where('page', ['id' => $page->id, 'type' => 'about_page', 'title' => 'عربي', 'slug' => 'about', 'content' => '<p>عربي</p>', 'meta_title' => 'About meta', 'meta_description' => 'Meta description', 'keywords' => 'store,about', 'meta_image' => ''])->where('lang', 'ar'));
    $this->put(route('custom-pages.update', $page), cmsPayload(['slug' => 'renamed']))->assertForbidden();
    expect($page->fresh()->slug)->toBe('about');
});
test('page validation refuses duplicate or unsafe slugs, languages and inaccessible uploads', function () {
    $page = cmsPage(['slug' => 'about']);
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $upload = Upload::factory()->create(['user_id' => createStoreUser()->id]);
    $this->post(route('custom-pages.store'), cmsPayload(['meta_image' => $upload->id, 'lang' => 'xx']))->assertSessionHasErrors(['slug', 'meta_image', 'lang']);
    $this->post(route('custom-pages.store'), cmsPayload(['slug' => '../../admin']))->assertSessionHasErrors('slug');
    expect(Page::count())->toBe(1)->and(PageTranslation::count())->toBe(0);
});
test('missing page edit and update are 404 and home content cannot use the custom page writer', function () {
    actingAsStoreUser(createStoreAdmin());
    $this->get(route('custom-pages.edit', 999999))->assertNotFound();
    $this->put(route('custom-pages.update', 999999), cmsPayload())->assertNotFound();
    $home = cmsPage(['type' => 'home_page', 'slug' => 'home']);
    $this->put(route('custom-pages.update', $home), cmsPayload(['slug' => 'home']))->assertForbidden();
    expect($home->fresh()->content)->toBe('Old content');
});
test('custom pages refuse unauthorized callers without records or translation changes', function (string $caller) {
    $page = cmsPage();
    actingAsRefusedStoreCaller($caller);
    foreach ([$this->get(route('custom-pages.create')), $this->get(route('custom-pages.edit', $page)), $this->post(route('custom-pages.store'), cmsPayload()), $this->put(route('custom-pages.update', $page), cmsPayload())] as $response) {
        assertStoreCallerRefused($response, $caller);
    }
    expect(Page::count())->toBe(1)->and($page->fresh()->title)->toBe('Old')->and(PageTranslation::count())->toBe(0);
})->with('refused store admin callers');
