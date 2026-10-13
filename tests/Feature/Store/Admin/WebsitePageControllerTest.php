<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->page = Page::forceCreate(['type' => 'custom_page', 'title' => 'About us', 'slug' => 'about-us', 'content' => '<p>About <b>us</b></p>', 'meta_title' => 'About meta', 'meta_description' => 'About description', 'keywords' => 'about, store']);
    PageTranslation::create(['page_id' => $this->page->id, 'lang' => 'en', 'title' => 'About us', 'content' => '<p>About <b>us</b></p>']);
    PageTranslation::create(['page_id' => $this->page->id, 'lang' => 'ar', 'title' => 'من نحن', 'content' => '<p>عنا</p>']);
    $this->policy = Page::forceCreate(['type' => 'return_policy_page', 'title' => 'Return Policy', 'slug' => 'return-policy', 'content' => '<p>Returns</p>']);
    $this->payload = ['title' => 'Our story', 'slug' => 'our-story', 'content' => '<p>Since <strong>1990</strong></p>', 'meta_title' => 'Story', 'meta_description' => 'Our story', 'keywords' => 'story', 'meta_image' => null];
});

/** @return array<string, list<array<string, mixed>>> */
function websitePagesSnapshot(): array
{
    return collect(['pages', 'page_translations'])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
}

test('the website page list renders with the full narrow row, translated titles and real pagination', function () {
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    $home = Page::forceCreate(['type' => 'home_page', 'title' => 'Home', 'slug' => 'home']);
    $slugless = Page::forceCreate(['type' => 'custom_page', 'title' => 'Draft']);
    $this->get(route('website.pages'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Pages/Index')
        ->has('pages.data', 4)
        ->has('pages.data.0', fn (Assert $row) => $row->where('id', $this->page->id)->where('title', 'About us')->where('slug', 'about-us')->where('type', 'custom_page')->where('public_url', route('custom-pages.show_custom_page', 'about-us')))
        ->where('pages.data.1.title', 'Return Policy')
        ->where('pages.data.2.public_url', route('home'))
        ->where('pages.data.2.id', $home->id)
        ->where('pages.data.3.id', $slugless->id)
        ->where('pages.data.3.public_url', null)
        ->where('filters', ['per_page' => 20]));
    $this->withSession(['locale' => 'ar'])->get(route('website.pages'))->assertInertia(fn (Assert $page) => $page
        ->where('pages.data.0.title', 'من نحن')->where('pages.data.1.title', 'Return Policy'));
    foreach (range(1, 22) as $i) {
        Page::forceCreate(['type' => 'custom_page', 'title' => 'Noise '.$i, 'slug' => 'noise-'.$i]);
    }
    assertPagesWithoutOverlap(fn ($page) => $this->get(route('website.pages', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('pages.data'), 'id'), 26, 20);
    $this->get(route('website.pages', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('pages.data', 10)->where('filters.per_page', 10));
    foreach ([11, 0, 1000] as $size) {
        $this->getJson(route('website.pages', ['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
});

test('the page create screen renders with the storefront address prefix', function () {
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $this->get(route('custom-pages.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Pages/Create')->where('homeUrl', route('home')));
});

test('the page edit screen sends the language fields, cleaned content, shared fields and permitted preview', function () {
    $staff = actingAsStoreUser(createStoreStaff('edit_website_page'));
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Meta', 'file_size' => 12, 'external_link' => 'https://example.com/meta.png']);
    $this->page->forceFill(['meta_image' => (string) $image->id])->save();
    PageTranslation::query()->where('page_id', $this->page->id)->where('lang', 'en')->update(['content' => '<p>About <b>us</b></p><script>alert(1)</script>']);
    $this->get(route('custom-pages.edit', ['page' => 'about-us', 'lang' => 'en']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Website/Pages/Edit')
        ->has('page', fn (Assert $form) => $form->where('id', $this->page->id)->where('type', 'custom_page')->where('slug', 'about-us')
            ->where('meta_title', 'About meta')->where('meta_description', 'About description')->where('keywords', 'about, store')
            ->where('meta_image', (string) $image->id)->where('public_url', route('custom-pages.show_custom_page', 'about-us')))
        ->where('title', 'About us')
        ->where('content', '<p>About <b>us</b></p>')
        ->where('lang', 'en')
        ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']])
        ->where('homeUrl', route('home'))
        ->where('initialFiles', ['meta_image' => [['id' => $image->id, 'name' => 'Meta', 'type' => 'image', 'size' => 12, 'url' => 'https://example.com/meta.png']]]));
    $this->get(route('custom-pages.edit', ['page' => 'about-us', 'lang' => 'ar']))->assertInertia(fn (Assert $page) => $page
        ->where('title', 'من نحن')->where('content', '<p>عنا</p>')->where('lang', 'ar'));
    $this->get(route('custom-pages.edit', 'about-us'))->assertInertia(fn (Assert $page) => $page->where('lang', 'en'));
    $this->get(route('custom-pages.edit', 'return-policy'))->assertInertia(fn (Assert $page) => $page
        ->where('page.type', 'return_policy_page')->where('title', 'Return Policy')->where('content', '<p>Returns</p>')->where('page.meta_title', '')->where('page.meta_image', ''));
    $this->getJson(route('custom-pages.edit', ['page' => 'about-us', 'lang' => 'fr']))->assertUnprocessable()->assertJsonValidationErrors('lang');
});

test('the home page row opens the home settings screen', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    Page::forceCreate(['type' => 'home_page', 'title' => 'Home', 'slug' => 'start']);
    $this->get(route('custom-pages.edit', 'start'))->assertRedirect(route('website.home.edit'));
});

test('a new page is a custom page with its default-language translation, a normalised slug and cleaned content', function () {
    $staff = actingAsStoreUser(createStoreStaff('add_website_page'));
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('custom-pages.store'), [...$this->payload, 'slug' => 'Our Story!', 'content' => '<p>Since <strong>1990</strong></p><img src="x" onfocus="alert(1)">', 'meta_image' => $image->id])
        ->assertRedirect(route('website.pages'));
    $page = Page::query()->where('slug', 'Our-Story')->sole();
    expect($page->type)->toBe('custom_page')
        ->and($page->title)->toBe('Our story')
        ->and($page->content)->toBe('<p>Since <strong>1990</strong></p><img src="x">')
        ->and($page->meta_title)->toBe('Story')->and($page->meta_description)->toBe('Our story')->and($page->keywords)->toBe('story')
        ->and($page->meta_image)->toBe((string) $image->id)
        ->and(PageTranslation::query()->where('page_id', $page->id)->get(['lang', 'title', 'content'])->toArray())
        ->toBe([['lang' => 'en', 'title' => 'Our story', 'content' => '<p>Since <strong>1990</strong></p><img src="x">']]);
});

test('a page slug must be unique, non-empty after normalisation and short enough', function (string $slug) {
    actingAsStoreUser(createStoreStaff('add_website_page', 'edit_website_page'));
    Page::forceCreate(['type' => 'custom_page', 'title' => 'Taken', 'slug' => 'taken']);
    $before = websitePagesSnapshot();
    $this->post(route('custom-pages.store'), [...$this->payload, 'slug' => $slug])->assertSessionHasErrors('slug');
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => $slug, 'lang' => 'en'])->assertSessionHasErrors('slug');
    expect(websitePagesSnapshot())->toEqual($before);
})->with([
    'taken' => ['taken'],
    'taken after normalisation' => ['tak!en'],
    'another page\'s policy slug' => ['return-policy'],
    'only symbols' => ['!!!'],
    'too long' => [str_repeat('a', 256)],
]);

test('page fields are validated before any write', function (string $field, mixed $value) {
    config(['waf.enabled' => false]);
    actingAsStoreUser(createStoreStaff('add_website_page', 'edit_website_page'));
    $before = websitePagesSnapshot();
    $this->post(route('custom-pages.store'), [...$this->payload, $field => $value])->assertSessionHasErrors($field);
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, $field => $value, 'lang' => 'en'])->assertSessionHasErrors($field);
    expect(websitePagesSnapshot())->toEqual($before);
})->with([
    'missing title' => ['title', null],
    'long title' => ['title', str_repeat('t', 256)],
    'missing content' => ['content', null],
    'content only a script' => ['content', '<script>alert(1)</script>'],
    'long meta description' => ['meta_description', str_repeat('d', 1001)],
    'long keywords' => ['keywords', str_repeat('k', 1001)],
    'meta image' => ['meta_image', 'abc'],
    'missing meta image' => ['meta_image', 999999],
]);

test('a default-language update rewrites the base page and its translation, keeping the URL slug rules', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => 'Our Story', 'lang' => 'en'])->assertRedirect(route('website.pages'));
    $page = $this->page->fresh();
    expect($page->slug)->toBe('Our-Story')->and($page->title)->toBe('Our story')->and($page->content)->toBe('<p>Since <strong>1990</strong></p>')
        ->and($page->meta_title)->toBe('Story')
        ->and(PageTranslation::query()->where('page_id', $page->id)->where('lang', 'en')->value('title'))->toBe('Our story')
        ->and(PageTranslation::query()->where('page_id', $page->id)->where('lang', 'ar')->value('title'))->toBe('من نحن');
    $this->put(route('custom-pages.update', $page), [...$this->payload, 'slug' => 'Our-Story', 'lang' => 'en'])->assertRedirect(route('website.pages'));
});

test('another language updates only its translation and the shared fields', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->patch(route('custom-pages.update', $this->page), [...$this->payload, 'title' => 'قصتنا', 'content' => '<p>منذ 1990</p>', 'slug' => 'about-us', 'lang' => 'ar'])->assertRedirect(route('website.pages'));
    $page = $this->page->fresh();
    expect($page->title)->toBe('About us')->and($page->content)->toBe('<p>About <b>us</b></p>')
        ->and($page->meta_title)->toBe('Story')->and($page->keywords)->toBe('story')
        ->and(PageTranslation::query()->where('page_id', $page->id)->where('lang', 'ar')->first(['title', 'content'])->toArray())->toBe(['title' => 'قصتنا', 'content' => '<p>منذ 1990</p>']);
});

test('a policy page keeps its slug and a home page is not edited through the page form', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $policyPayload = array_diff_key([...$this->payload, 'lang' => 'en'], ['slug' => true]);
    $this->put(route('custom-pages.update', $this->policy), $policyPayload)->assertRedirect(route('website.pages'));
    expect($this->policy->fresh()->slug)->toBe('return-policy')->and($this->policy->fresh()->title)->toBe('Our story');
    $before = websitePagesSnapshot();
    $this->put(route('custom-pages.update', $this->policy), [...$policyPayload, 'slug' => 'renamed'])->assertSessionHasErrors('slug');
    expect(websitePagesSnapshot())->toEqual($before);
    $home = Page::forceCreate(['type' => 'home_page', 'title' => 'Home', 'slug' => 'home']);
    $before = websitePagesSnapshot();
    $this->put(route('custom-pages.update', $home), $policyPayload)->assertForbidden();
    $this->put(route('custom-pages.update', $home), [...$this->payload, 'lang' => 'en'])->assertSessionHasErrors('slug');
    expect(websitePagesSnapshot())->toEqual($before);
});

test('page content is cleaned of scripts, handlers and unsafe URLs while legacy formatting is kept', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('add_website_page', 'edit_website_page'));
    $kept = '<h2>Kept</h2><p style="text-align: right"><b>bold</b> <i>italic</i></p><table><tbody><tr><td>Cell</td></tr></tbody></table>';
    $this->post(route('custom-pages.store'), [...$this->payload, 'content' => $kept.$hostile])->assertSessionHasNoErrors()->assertRedirect();
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => 'about-us', 'content' => $kept.$hostile, 'lang' => 'ar'])->assertSessionHasNoErrors()->assertRedirect();
    foreach ([Page::query()->where('slug', 'our-story')->sole()->content, PageTranslation::query()->where('page_id', $this->page->id)->where('lang', 'ar')->value('content')] as $stored) {
        expect($stored)->toStartWith($kept)->not->toContain($removed)->and(clean_html($stored))->toBe($stored);
    }
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'obfuscated javascript link' => ['<a href="java&#x09;script:alert(1)">Link</a>', 'script:'],
    'iframe' => ['<iframe src="https://example.com/embed"></iframe>', 'iframe'],
    'form' => ['<form action="https://example.com"><input name="x"></form>', 'form'],
]);

test('the WAF still refuses a raw script payload in page content before any write', function () {
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $before = websitePagesSnapshot();
    $this->post(route('custom-pages.store'), [...$this->payload, 'content' => '<p>x</p><script>alert(1)</script>'])->assertForbidden();
    expect(websitePagesSnapshot())->toEqual($before);
});

test('page meta images allow own and library images while a retained foreign image grants no replacement or preview access', function () {
    $staff = actingAsStoreUser(createStoreStaff('add_website_page', 'edit_website_page'));
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $otherForeign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $document = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    foreach ([$foreign, $document] as $upload) {
        $before = websitePagesSnapshot();
        $this->post(route('custom-pages.store'), [...$this->payload, 'meta_image' => $upload->id])->assertSessionHasErrors('meta_image');
        expect(websitePagesSnapshot())->toEqual($before);
    }
    $this->page->forceFill(['meta_image' => (string) $foreign->id])->save();
    $this->get(route('custom-pages.edit', 'about-us'))->assertInertia(fn (Assert $page) => $page->where('initialFiles.meta_image', [])->where('page.meta_image', (string) $foreign->id));
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => 'about-us', 'meta_image' => $foreign->id, 'lang' => 'en'])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->page->fresh()->meta_image)->toBe((string) $foreign->id);
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => 'about-us', 'meta_image' => $otherForeign->id, 'lang' => 'en'])->assertSessionHasErrors('meta_image');
    actingAsStoreUser(createStoreStaff('edit_website_page', 'view_uploads'));
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'slug' => 'about-us', 'meta_image' => $otherForeign->id, 'lang' => 'en'])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->page->fresh()->meta_image)->toBe((string) $otherForeign->id);
});

test('deleting a custom page removes it with its translations and returns to the list query', function () {
    actingAsStoreUser(createStoreStaff('delete_website_page'));
    $this->delete(route('custom-pages.destroy', ['page' => $this->page, 'page_number' => 2]))->assertRedirect(route('website.pages', ['page_number' => 2]));
    expect(Page::query()->find($this->page->id))->toBeNull()
        ->and(PageTranslation::query()->where('page_id', $this->page->id)->count())->toBe(0)
        ->and($this->policy->fresh())->not->toBeNull();
});

test('a policy or home page cannot be deleted', function () {
    actingAsStoreUser(createStoreStaff('delete_website_page'));
    $home = Page::forceCreate(['type' => 'home_page', 'title' => 'Home', 'slug' => 'home']);
    $before = websitePagesSnapshot();
    $this->delete(route('custom-pages.destroy', $this->policy))->assertForbidden();
    $this->delete(route('custom-pages.destroy', $home))->assertForbidden();
    expect(websitePagesSnapshot())->toEqual($before);
});

test('every page action returns 404 for a missing page', function () {
    actingAsStoreUser(createStoreStaff('edit_website_page', 'delete_website_page'));
    $before = websitePagesSnapshot();
    $this->get(route('custom-pages.edit', 'missing-page'))->assertNotFound();
    $this->put(route('custom-pages.update', 999999), [...$this->payload, 'lang' => 'en'])->assertNotFound();
    $this->delete(route('custom-pages.destroy', 999999))->assertNotFound();
    expect(websitePagesSnapshot())->toEqual($before);
});

test('each page action refuses unauthorized callers without changes', function (string $caller) {
    $before = websitePagesSnapshot();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('website.pages')), $caller);
    assertStoreCallerRefused($this->get(route('custom-pages.create')), $caller);
    assertStoreCallerRefused($this->post(route('custom-pages.store'), $this->payload), $caller);
    assertStoreCallerRefused($this->get(route('custom-pages.edit', 'about-us')), $caller);
    assertStoreCallerRefused($this->put(route('custom-pages.update', $this->page), [...$this->payload, 'lang' => 'en']), $caller);
    assertStoreCallerRefused($this->delete(route('custom-pages.destroy', $this->page)), $caller);
    expect(websitePagesSnapshot())->toEqual($before);
})->with('refused store admin callers');

test('each page permission authorizes only its own actions', function () {
    $before = websitePagesSnapshot();
    actingAsStoreUser(createStoreStaff('view_all_website_pages'));
    $this->post(route('custom-pages.store'), $this->payload)->assertForbidden();
    $this->get(route('custom-pages.edit', 'about-us'))->assertForbidden();
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $this->get(route('website.pages'))->assertForbidden();
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'lang' => 'en'])->assertForbidden();
    actingAsStoreUser(createStoreStaff('edit_website_page'));
    $this->delete(route('custom-pages.destroy', $this->page))->assertForbidden();
    actingAsStoreUser(createStoreStaff('delete_website_page'));
    $this->get(route('custom-pages.create'))->assertForbidden();
    expect(websitePagesSnapshot())->toEqual($before);
});

test('WMS authentication grants no page action and no store writes', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $before = websitePagesSnapshot();
    $this->get(route('website.pages'))->assertRedirect(route('shop.login'));
    $this->post(route('custom-pages.store'), $this->payload)->assertRedirect(route('shop.login'));
    $this->put(route('custom-pages.update', $this->page), [...$this->payload, 'lang' => 'en'])->assertRedirect(route('shop.login'));
    $this->delete(route('custom-pages.destroy', $this->page))->assertRedirect(route('shop.login'));
    expect(websitePagesSnapshot())->toEqual($before);
});

test('saved pages still render on the storefront and the mobile page exactly as before', function () {
    seedStoreCurrency();
    actingAsStoreUser(createStoreStaff('add_website_page'));
    $this->post(route('custom-pages.store'), [...$this->payload, 'content' => '<h2>Story</h2><p style="text-align: center">Since <strong>1990</strong></p>'])->assertSessionHasNoErrors()->assertRedirect();
    auth('store')->logout();
    $this->get(route('custom-pages.show_custom_page', 'our-story'))->assertOk()
        ->assertSee('<h2>Story</h2><p style="text-align: center">Since <strong>1990</strong></p>', false)
        ->assertSee('<meta name="twitter:title" content="Story">', false);
    $this->get(route('mobile.custom-pages', 'our-story'))->assertOk()
        ->assertSee('<h2>Story</h2><p style="text-align: center">Since <strong>1990</strong></p>', false);
    $this->get(route('custom-pages.show_custom_page', 'missing-page'))->assertNotFound();
});

test('the page routes keep every consumed name and drop the resource duplicates', function () {
    expect(route('website.pages', [], false))->toBe('/temp/admin/website/pages')
        ->and(route('custom-pages.create', [], false))->toBe('/temp/admin/website/custom-pages/create')
        ->and(route('custom-pages.store', [], false))->toBe('/temp/admin/website/custom-pages')
        ->and(route('custom-pages.edit', 'about-us', false))->toBe('/temp/admin/website/custom-pages/edit/about-us')
        ->and(route('custom-pages.update', 5, false))->toBe('/temp/admin/website/custom-pages/5')
        ->and(route('custom-pages.destroy', 5, false))->toBe('/temp/admin/website/custom-pages/destroy/5')
        ->and(route('website.home.edit', [], false))->toBe('/temp/admin/website/custom-pages/edit/home')
        ->and(route('custom-pages.show_custom_page', 'about-us', false))->toBe('/temp/about-us')
        ->and(Route::has('custom-pages.index'))->toBeFalse()
        ->and(Route::has('custom-pages.show'))->toBeFalse();
    foreach (['shop.backend.website_settings.pages.index', 'shop.backend.website_settings.pages.create', 'shop.backend.website_settings.pages.edit'] as $view) {
        expect(view()->exists($view))->toBeFalse();
    }
    actingAsStoreUser(createStoreStaff('view_all_website_pages', 'edit_website_page', 'delete_website_page'));
    $this->get('/temp/admin/website/custom-pages')->assertMethodNotAllowed();
    $this->get('/temp/admin/website/custom-pages/'.$this->page->id.'/edit')->assertNotFound();
    $this->post('/temp/admin/website/custom-pages/destroy/'.$this->page->id)->assertMethodNotAllowed();
    expect($this->page->fresh())->not->toBeNull();
});
