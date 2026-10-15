<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Permission;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function adminBlogCategory(string $name = 'Recipes'): BlogCategory
{
    return BlogCategory::forceCreate(['category_name' => $name, 'slug' => 'recipes']);
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function adminBlogForm(BlogCategory $category, array $overrides = []): array
{
    return [
        'category_id' => $category->id, 'title' => 'Fresh recipes', 'slug' => 'fresh-recipes',
        'short_description' => 'A short introduction', 'description' => '<p>Safe <strong>recipe</strong></p>',
        'banner' => '', 'meta_img' => '', 'meta_title' => 'Recipe metadata',
        'meta_description' => 'A recipe description', 'meta_keywords' => 'rice, food', ...$overrides,
    ];
}

/** @param array<string, mixed> $overrides */
function adminBlog(BlogCategory $category, array $overrides = []): Blog
{
    return Blog::forceCreate(adminBlogForm($category, ['banner' => null, 'meta_img' => null, 'status' => 1, ...$overrides]));
}

/** @return array<string, array<int, mixed>> */
function blogAdminSnapshot(): array
{
    return collect(['blogs', 'blog_categories'])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toArray()])->all();
}

/**
 * HTML the shared Tiptap editor (StoreRichTextField) posts for formatting,
 * alignment, colours, a table, a link and a canonical upload image, as a
 * browser serialises it (ProseMirror writes styles through the CSSOM).
 */
function blogEditorHtml(string $imageUrl): string
{
    return '<h2>Fresh <strong>rice</strong></h2>'
        .'<p style="text-align: center;"><em>Centred</em> <u>under</u> <s>struck</s> <span style="color: rgb(192, 0, 0);">red</span> <span style="background-color: rgb(255, 255, 0);">marked</span></p>'
        .'<ul><li><p>One</p></li></ul><ol><li><p>First</p></li></ol><blockquote><p>Quote</p></blockquote>'
        .'<table style="min-width: 50px;"><colgroup><col style="min-width: 25px;"><col style="min-width: 25px;"></colgroup><tbody>'
        .'<tr><th colspan="1" rowspan="1"><p>Head</p></th><th colspan="1" rowspan="1"><p>Cell</p></th></tr>'
        .'<tr><td colspan="2" rowspan="1"><p>Wide</p></td></tr></tbody></table>'
        .'<p><a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/rice">Link</a></p>'
        .'<p>Photo <img src="'.$imageUrl.'" alt="rice.png"></p>';
}

/**
 * Representative markup the retired Blade editor (summernote, `aiz-text-editor`)
 * stored before rich text was cleaned on write: legacy tags, an alignment and an
 * indent, a `<font>` colour, a bordered table, a resized image and a video embed.
 */
function legacyBlogEditorHtml(): string
{
    return '<h2>Fresh <b>rice</b></h2><p style="text-align: center;"><font color="#ff0000">Red</font> and '
        .'<span style="background-color: rgb(255, 255, 0);">marked</span></p><p style="margin-left: 25px;">Indented <u>under</u> <i>italic</i></p>'
        .'<ul><li>One</li></ul><ol><li>First</li></ol><blockquote>Quote</blockquote><pre>code</pre>'
        .'<table class="table table-bordered"><tbody><tr><td>A</td><td><br></td></tr></tbody></table>'
        .'<p><a href="https://example.com/rice" target="_blank">Link</a></p><p><img src="https://example.com/uploads/all/legacy.png" style="width: 50%;"></p>'
        .'<div class="embed-responsive embed-responsive-16by9"><iframe class="embed-responsive-item" src="//www.youtube.com/embed/abc"></iframe></div>';
}

test('blog posts list renders its complete narrow summary and searches stored Arabic content', function () {
    $category = adminBlogCategory('وصفات');
    $blog = adminBlog($category, ['title' => 'وصفة الأرز']);
    adminBlog($category, ['title' => 'Unrelated', 'slug' => 'other']);
    $deleted = adminBlog($category, ['title' => 'Deleted', 'slug' => 'deleted']);
    $deleted->delete();
    actingAsStoreUser(createStoreStaff('view_blogs'));
    $this->get(route('blog.index', ['search' => 'الأرز']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Blog/Posts/Index')->where('filters', ['per_page' => 20, 'search' => 'الأرز'])
        ->has('blogs.data', 1)->has('blogs.data.0', fn (Assert $row) => $row
        ->where('id', $blog->id)->where('title', 'وصفة الأرز')->where('status', true)
        ->where('category', ['id' => $category->id, 'category_name' => 'وصفات'])
        ->where('public_url', route('blog.details', 'fresh-recipes'))));
});

test('blog posts paginate without overlap and validate filters', function () {
    $category = adminBlogCategory();
    foreach (range(1, 23) as $number) {
        adminBlog($category, ['title' => 'Post '.$number, 'slug' => 'post-'.$number]);
    }
    actingAsStoreUser(createStoreStaff('view_blogs'));
    assertPagesWithoutOverlap(fn (int $page) => $this->get(route('blog.index', ['page' => $page])), fn ($response) => array_column($response->inertiaProps('blogs.data'), 'id'), 23, 20);
    $this->get(route('blog.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('blogs.data', 10)->where('filters.per_page', 10));
    $this->getJson(route('blog.index', ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson(route('blog.index', ['search' => str_repeat('x', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('blog creation renders the Inertia editor form with the live categories in their Blade order', function () {
    $first = adminBlogCategory('وصفات');
    $deleted = BlogCategory::forceCreate(['category_name' => 'Deleted', 'slug' => 'deleted']);
    $deleted->delete();
    $last = BlogCategory::forceCreate(['category_name' => 'Ahead', 'slug' => 'ahead']);
    actingAsStoreUser(createStoreStaff('add_blog'));
    $this->get(route('blog.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Blog/Posts/Create')
        ->where('categories', [['id' => $first->id, 'category_name' => 'وصفات'], ['id' => $last->id, 'category_name' => 'Ahead']])
        ->missing('blog')->missing('initialFiles'));
});

test('blog editing renders the bound record with every form field, a cleaned body and permitted image previews', function () {
    $category = adminBlogCategory();
    $staff = createStoreStaff('edit_blog');
    $banner = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Banner', 'file_size' => 128, 'external_link' => 'https://example.com/banner.png']);
    $meta = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'Meta', 'file_size' => 64, 'external_link' => 'https://example.com/meta.png']);
    $blog = adminBlog($category, [
        'title' => 'وصفة الأرز', 'short_description' => 'وصفة طازجة', 'banner' => $banner->id, 'meta_img' => $meta->id, 'status' => 0,
        'description' => '<p dir="rtl" style="text-align: center">أرز</p><img src="x" onerror="alert(1)"><script>alert(1)</script>',
    ]);
    adminBlog($category, ['title' => 'Other', 'slug' => 'other']);
    actingAsStoreUser($staff);
    $this->get(route('blog.edit', $blog))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Blog/Posts/Edit')
        ->has('blog', fn (Assert $record) => $record
            ->where('id', $blog->id)->where('title', 'وصفة الأرز')->where('slug', 'fresh-recipes')
            ->where('short_description', 'وصفة طازجة')->where('status', false)
            ->where('category', ['id' => $category->id, 'category_name' => 'Recipes'])->where('category_id', $category->id)
            ->where('public_url', route('blog.details', 'fresh-recipes'))
            ->where('description', '<p dir="rtl" style="text-align: center">أرز</p><img src="x">')
            ->where('banner', (string) $banner->id)->where('meta_img', (string) $meta->id)
            ->where('meta_title', 'Recipe metadata')->where('meta_description', 'A recipe description')->where('meta_keywords', 'rice, food'))
        ->where('categories', [['id' => $category->id, 'category_name' => 'Recipes']])
        ->where('initialFiles', [
            'banner' => [['id' => $banner->id, 'name' => 'Banner', 'type' => 'image', 'size' => 128, 'url' => 'https://example.com/banner.png']],
            'meta_img' => [['id' => $meta->id, 'name' => 'Meta', 'type' => 'image', 'size' => 64, 'url' => 'https://example.com/meta.png']],
        ]));
});

test('blog editing sends empty optional fields as empty strings and no previews', function () {
    $blog = adminBlog(adminBlogCategory(), ['short_description' => null, 'description' => null, 'meta_title' => null, 'meta_description' => null, 'meta_keywords' => null]);
    actingAsStoreUser(createStoreStaff('edit_blog'));
    $this->get(route('blog.edit', $blog))->assertInertia(fn (Assert $page) => $page
        ->where('blog.short_description', null)->where('blog.description', '')->where('blog.banner', '')->where('blog.meta_img', '')
        ->where('blog.meta_title', '')->where('blog.meta_description', '')->where('blog.meta_keywords', '')
        ->where('initialFiles', ['banner' => [], 'meta_img' => []]));
});

test('blog creation preserves database language and sanitizes rich content with the legacy default publication', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $category = adminBlogCategory();
    $upload = Upload::factory()->create(['type' => 'image']);
    actingAsStoreUser(createStoreStaff('add_blog', 'view_uploads'));
    $this->post(route('blog.store'), adminBlogForm($category, [
        'title' => 'وصفة الأرز', 'short_description' => 'وصفة طازجة', 'banner' => $upload->id, 'meta_img' => $upload->id,
        'description' => '<p dir="rtl">أرز <strong>لذيذ</strong></p><script>alert(1)</script><img src=x onerror=alert(1)>',
        'status' => 0, 'deleted_at' => now(),
    ]))->assertRedirect(route('blog.index'));
    $blog = Blog::query()->sole();
    expect($blog->title)->toBe('وصفة الأرز')->and($blog->short_description)->toBe('وصفة طازجة')
        ->and($blog->status)->toBe(1)->and($blog->deleted_at)->toBeNull()
        ->and($blog->description)->toContain('dir="rtl"', '<strong>لذيذ</strong>')->not->toContain('script', 'onerror');
    $this->assertDatabaseHas('blogs', ['id' => $blog->id, 'banner' => $upload->id, 'meta_img' => $upload->id]);
});

test('blog update keeps canonical URLs and publication status unless explicitly changed by publisher', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category, ['status' => 0]);
    $other = adminBlog($category, ['title' => 'Other', 'slug' => 'other']);
    actingAsStoreUser(createStoreStaff('edit_blog', 'view_uploads'));
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['title' => 'Changed title', 'status' => 1]))->assertRedirect(route('blog.index'));
    expect($blog->fresh()->title)->toBe('Changed title')->and($blog->fresh()->slug)->toBe('fresh-recipes')
        ->and($blog->fresh()->status)->toBe(0)->and($other->fresh()->title)->toBe('Other');
});

test('blog writes authoritatively reject invalid fields without touching existing records', function (string $action, string $field, mixed $invalid) {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    actingAsStoreUser(createStoreStaff('add_blog', 'edit_blog', 'view_uploads'));
    $form = adminBlogForm($category, ['slug' => $action === 'store' ? 'new-post' : $blog->slug, $field => $invalid]);
    $response = $action === 'store' ? $this->postJson(route('blog.store'), $form) : $this->putJson(route('blog.update', $blog), $form);
    $response->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('blogs', 1);
    expect($blog->fresh()->title)->toBe('Fresh recipes')->and($blog->fresh()->slug)->toBe('fresh-recipes');
})->with(['store', 'update'])->with([
    'empty title' => ['title', ''], 'title array' => ['title', ['bad']], 'title width' => ['title', str_repeat('x', 256)],
    'missing category' => ['category_id', 999999], 'invalid slug' => ['slug', 'bad/slug'], 'empty slug' => ['slug', ''],
    'slug width' => ['slug', str_repeat('x', 256)], 'description array' => ['description', ['unsafe']],
    'summary width' => ['short_description', str_repeat('x', 65536)], 'meta title width' => ['meta_title', str_repeat('x', 256)],
    'meta description width' => ['meta_description', str_repeat('x', 65536)], 'meta keywords width' => ['meta_keywords', str_repeat('x', 65536)],
]);

test('blog writes reject deleted categories, duplicate slugs and non-image uploads', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    $deleted = adminBlogCategory('Deleted');
    $deleted->delete();
    $document = Upload::factory()->create(['type' => 'document']);
    actingAsStoreUser(createStoreStaff('add_blog', 'edit_blog', 'view_uploads'));
    $this->postJson(route('blog.store'), adminBlogForm($category, ['category_id' => $deleted->id, 'banner' => $document->id, 'meta_img' => 999999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'slug', 'banner', 'meta_img']);
    $this->putJson(route('blog.update', $blog), adminBlogForm($category))->assertRedirect(route('blog.index'));
    $this->assertDatabaseCount('blogs', 1);
});

test('an accountant may attach their own image but cannot attach another owners image', function () {
    $category = adminBlogCategory();
    $accountant = createStoreUser('accountant');
    Permission::findOrCreate('add_blog', 'store');
    $accountant->givePermissionTo('add_blog');
    $own = Upload::factory()->create(['type' => 'image', 'user_id' => $accountant->id]);
    $foreign = Upload::factory()->create(['type' => 'image']);
    actingAsStoreUser($accountant);
    $this->postJson(route('blog.store'), adminBlogForm($category, ['banner' => $foreign->id, 'meta_img' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['banner', 'meta_img']);
    $this->assertDatabaseCount('blogs', 0);
    $this->post(route('blog.store'), adminBlogForm($category, ['banner' => $own->id, 'meta_img' => $own->id]))->assertRedirect(route('blog.index'));
    $this->assertDatabaseHas('blogs', ['banner' => $own->id, 'meta_img' => $own->id]);
});

test('only the publication action changes status with a boolean and preserves current query', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    actingAsStoreUser(createStoreStaff('publish_blog'));
    $this->putJson(route('blog.change-status', $blog), ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    expect($blog->fresh()->status)->toBe(1);
    $this->put(route('blog.change-status', [$blog, 'search' => 'Fresh', 'page' => 2]), ['status' => 0])
        ->assertRedirect(route('blog.index', ['search' => 'Fresh', 'page' => 2]));
    expect($blog->fresh()->status)->toBe(0);
    $this->put(route('blog.change-status', $blog), ['status' => 1])->assertRedirect(route('blog.index'));
    expect($blog->fresh()->status)->toBe(1);
});

test('blog delete soft deletes only the bound record and redirects to the prefixed filtered index', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    $other = adminBlog($category, ['slug' => 'other']);
    actingAsStoreUser(createStoreStaff('delete_blog'));
    $this->delete(route('blog.destroy', [$blog, 'search' => 'Fresh', 'page' => 2]))->assertRedirect(route('blog.index', ['search' => 'Fresh', 'page' => 2]));
    $this->assertSoftDeleted($blog);
    expect($other->fresh())->not->toBeNull();
});

test('missing and deleted blogs are refused by bound admin routes', function (string $action) {
    $blog = adminBlog(adminBlogCategory());
    $blog->delete();
    actingAsStoreUser(createStoreStaff('edit_blog', 'delete_blog', 'publish_blog'));
    foreach ([$blog->id, 999999] as $id) {
        (match ($action) {
            'edit' => $this->get(route('blog.edit', $id)),
            'update' => $this->put(route('blog.update', $id), []),
            'destroy' => $this->delete(route('blog.destroy', $id)),
            'status' => $this->put(route('blog.change-status', $id), ['status' => 0]),
        })->assertNotFound();
    }
})->with(['edit', 'update', 'destroy', 'status']);

foreach (['index', 'create', 'store', 'edit', 'update', 'destroy', 'status'] as $action) {
    test("blog {$action} refuses unauthorized callers and leaves all content unchanged", function (string $caller) use ($action) {
        $category = adminBlogCategory();
        $blog = adminBlog($category);
        $before = blogAdminSnapshot();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'index' => $this->get(route('blog.index')), 'create' => $this->get(route('blog.create')),
            'store' => $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'forbidden'])),
            'edit' => $this->get(route('blog.edit', $blog)), 'update' => $this->put(route('blog.update', $blog), adminBlogForm($category, ['title' => 'Forbidden'])),
            'destroy' => $this->delete(route('blog.destroy', $blog)), 'status' => $this->put(route('blog.change-status', $blog), ['status' => 0]),
        };
        assertStoreCallerRefused($response, $caller);
        $this->assertDatabaseCount('blogs', 1);
        expect($blog->fresh()->title)->toBe('Fresh recipes')->and($blog->fresh()->status)->toBe(1)->and($blog->fresh()->deleted_at)->toBeNull()
            ->and(blogAdminSnapshot())->toEqual($before);
    })->with('refused store admin callers');
}

test('public blog HTML, URL metadata and schema markup stay server rendered after admin writes', function () {
    $category = adminBlogCategory();
    actingAsStoreUser(createStoreStaff('add_blog', 'view_uploads'));
    $this->post(route('blog.store'), adminBlogForm($category))->assertRedirect(route('blog.index'));
    adminBlog($category, ['title' => 'Another recent post', 'slug' => 'another-recent-post']);
    $this->get(route('blog.details', 'fresh-recipes'))->assertOk()->assertViewIs('shop.frontend.blog.details')
        ->assertSee('<strong>recipe</strong>', false)->assertSee('Recipe metadata')->assertSee('itemprop="name"', false)
        ->assertSee('property="og:url" content="'.route('blog.details', 'fresh-recipes').'"', false)
        ->assertHeaderMissing('X-Inertia');
    $this->get(route('blog'))->assertOk()->assertViewIs('shop.frontend.blog.listing')->assertSee('Fresh recipes')->assertHeaderMissing('X-Inertia');
});

test('warehouse administrator sessions cannot access blog administration or mutate store content', function (string $action) {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    actingAsAdmin();
    $response = match ($action) {
        'index' => $this->get(route('blog.index')),
        'create' => $this->get(route('blog.create')),
        'store' => $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'forbidden'])),
        'edit' => $this->get(route('blog.edit', $blog)),
        'update' => $this->put(route('blog.update', $blog), adminBlogForm($category, ['title' => 'Forbidden'])),
        'destroy' => $this->delete(route('blog.destroy', $blog)),
        'status' => $this->put(route('blog.change-status', $blog), ['status' => 0]),
    };
    $response->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('blogs', 1);
    expect($blog->fresh()->title)->toBe('Fresh recipes')->and($blog->fresh()->status)->toBe(1)->and($blog->fresh()->deleted_at)->toBeNull();
})->with(['index', 'create', 'store', 'edit', 'update', 'destroy', 'status']);

test('blog writes strip scripts, handlers and unsafe URLs from the body', function (string $hostile, string $removed) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    actingAsStoreUser(createStoreStaff('add_blog', 'edit_blog'));
    $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'new-post', 'description' => '<p>Kept</p>'.$hostile]))->assertRedirect(route('blog.index'));
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['description' => '<p>Kept</p>'.$hostile]))->assertRedirect(route('blog.index'));
    foreach ([Blog::query()->where('slug', 'new-post')->sole(), $blog->fresh()] as $stored) {
        expect($stored->description)->toStartWith('<p>Kept</p>')->not->toContain($removed)
            ->and(clean_html($stored->description))->toBe($stored->description);
    }
})->with([
    'script element' => ['<script>alert(1)</script>', 'alert'],
    'image error handler' => ['<img src="x" onerror="alert(1)">', 'onerror'],
    'click handler' => ['<p onclick="alert(1)">Click</p>', 'onclick'],
    'javascript link' => ['<a href="javascript:alert(1)">Link</a>', 'javascript'],
    'obfuscated javascript link' => ['<a href="java&#x09;script:alert(1)">Link</a>', 'script:'],
    'javascript image' => ['<img src="javascript:alert(1)">', 'javascript'],
    'svg script' => ['<svg><script>alert(1)</script></svg>', 'svg'],
    'iframe' => ['<iframe src="https://example.com"></iframe>', 'iframe'],
    'style url' => ['<p style="background-image: url(https://example.com/x.png)">Styled</p>', 'url('],
    'html data image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=">', 'data:'],
]);

test('blog writes keep editor formatting, alignment, colours, tables, links and canonical images, and the WAF lets them through', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    $staff = createStoreStaff('add_blog', 'edit_blog');
    $image = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $html = blogEditorHtml('https://example.com/uploads/all/rice.png');
    $expected = '<h2>Fresh <strong>rice</strong></h2>'
        .'<p style="text-align: center"><em>Centred</em> <u>under</u> <s>struck</s> <span style="color: rgb(192, 0, 0)">red</span> <span style="background-color: rgb(255, 255, 0)">marked</span></p>'
        .'<ul><li><p>One</p></li></ul><ol><li><p>First</p></li></ol><blockquote><p>Quote</p></blockquote>'
        .'<table><colgroup><col><col></colgroup><tbody>'
        .'<tr><th colspan="1" rowspan="1"><p>Head</p></th><th colspan="1" rowspan="1"><p>Cell</p></th></tr>'
        .'<tr><td colspan="2" rowspan="1"><p>Wide</p></td></tr></tbody></table>'
        .'<p><a target="_blank" href="https://example.com/rice" rel="noopener noreferrer nofollow">Link</a></p>'
        .'<p>Photo <img src="https://example.com/uploads/all/rice.png" alt="rice.png"></p>';
    actingAsStoreUser($staff);
    $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'new-post', 'banner' => $image->id, 'description' => $html]))->assertRedirect(route('blog.index'));
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['description' => $html]))->assertRedirect(route('blog.index'));
    expect(Blog::query()->where('slug', 'new-post')->sole()->description)->toBe($expected)
        ->and($blog->fresh()->description)->toBe($expected);
    $this->get(route('blog.details', 'fresh-recipes'))->assertOk()->assertSee($expected, false);
});

test('the WAF still refuses a raw script payload before any blog write', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category);
    actingAsStoreUser(createStoreStaff('add_blog', 'edit_blog'));
    $before = blogAdminSnapshot();
    $hostile = '<p>Text</p><img src=x onerror=alert(1)><script>alert(1)</script>';
    $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'new-post', 'description' => $hostile]))->assertForbidden();
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['description' => $hostile]))->assertForbidden();
    expect(blogAdminSnapshot())->toEqual($before);
});

test('blog images allow own and library images while a retained foreign image grants no replacement or preview access', function () {
    $category = adminBlogCategory();
    $staff = createStoreStaff('add_blog', 'edit_blog');
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $otherForeign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $document = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    $blog = adminBlog($category, ['banner' => $foreign->id, 'meta_img' => $foreign->id]);
    actingAsStoreUser($staff);
    foreach ([$otherForeign, $document] as $upload) {
        $before = blogAdminSnapshot();
        $this->putJson(route('blog.update', $blog), adminBlogForm($category, ['banner' => $upload->id, 'meta_img' => $upload->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['banner', 'meta_img']);
        $this->postJson(route('blog.store'), adminBlogForm($category, ['slug' => 'new-post', 'banner' => $upload->id, 'meta_img' => $upload->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['banner', 'meta_img']);
        expect(blogAdminSnapshot())->toEqual($before);
    }
    $this->get(route('blog.edit', $blog))->assertInertia(fn (Assert $page) => $page
        ->where('blog.banner', (string) $foreign->id)->where('initialFiles', ['banner' => [], 'meta_img' => []]));
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['banner' => $foreign->id, 'meta_img' => $foreign->id]))->assertRedirect(route('blog.index'));
    expect($blog->fresh()->banner)->toBe($foreign->id)->and($blog->fresh()->meta_img)->toBe($foreign->id);
    $this->postJson(route('blog.store'), adminBlogForm($category, ['slug' => 'new-post', 'banner' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('banner');
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['banner' => $own->id, 'meta_img' => '']))->assertRedirect(route('blog.index'));
    expect($blog->fresh()->banner)->toBe($own->id)->and($blog->fresh()->meta_img)->toBeNull();
    actingAsStoreUser(createStoreStaff('add_blog', 'view_uploads'));
    $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'library-post', 'banner' => $otherForeign->id, 'meta_img' => $foreign->id]))->assertRedirect(route('blog.index'));
    $this->assertDatabaseHas('blogs', ['slug' => 'library-post', 'banner' => $otherForeign->id, 'meta_img' => $foreign->id]);
});

test('legacy Blade editor markup round-trips through the editor props without changing the public page', function () {
    $category = adminBlogCategory();
    $blog = adminBlog($category, ['description' => legacyBlogEditorHtml()]);
    $publicBody = function (): string {
        $html = $this->get(route('blog.details', 'fresh-recipes'))->assertOk()->getContent();
        preg_match('#<div class="mb-4 overflow-hidden">(.*?)</div>\s*<!-- Facebook Comment -->#s', $html, $match);

        return trim($match[1] ?? '');
    };
    $before = $publicBody();
    $expected = '<h2>Fresh <b>rice</b></h2><p style="text-align: center">Red and <span style="background-color: rgb(255, 255, 0)">marked</span></p>'
        .'<p style="margin-left: 25px">Indented <u>under</u> <i>italic</i></p><ul><li>One</li></ul><ol><li>First</li></ol><blockquote>Quote</blockquote><pre>code</pre>'
        .'<table class="table table-bordered"><tbody><tr><td>A</td><td><br></td></tr></tbody></table>'
        .'<p><a href="https://example.com/rice" target="_blank" rel="noopener noreferrer nofollow">Link</a></p><p><img src="https://example.com/uploads/all/legacy.png" style="width: 50%"></p>'
        .'<div class="embed-responsive embed-responsive-16by9"></div>';
    expect($before)->toBe($expected);
    actingAsStoreUser(createStoreStaff('edit_blog'));
    $prop = $this->get(route('blog.edit', $blog))->assertOk()->inertiaProps('blog.description');
    expect($prop)->toBe($expected);
    $this->put(route('blog.update', $blog), adminBlogForm($category, ['description' => $prop]))->assertRedirect(route('blog.index'));
    expect($blog->fresh()->description)->toBe($expected)->and($publicBody())->toBe($before);
});
