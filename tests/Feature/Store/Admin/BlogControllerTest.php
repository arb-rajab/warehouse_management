<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Permission;
use App\Models\Upload;
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

test('blog creation retains the approved Blade editor', function () {
    adminBlogCategory();
    actingAsStoreUser(createStoreStaff('add_blog', 'view_uploads'));
    $this->get(route('blog.create'))->assertOk()->assertViewIs('shop.backend.blog_system.blog.create');
});

test('blog editing retains the approved Blade editor and its bound record', function () {
    $blog = adminBlog(adminBlogCategory());
    actingAsStoreUser(createStoreStaff('edit_blog', 'view_uploads'));
    $this->get(route('blog.edit', $blog))->assertOk()->assertViewIs('shop.backend.blog_system.blog.edit')->assertViewHas('blog', fn ($record) => $record->id === $blog->id);
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
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'index' => $this->get(route('blog.index')), 'create' => $this->get(route('blog.create')),
            'store' => $this->post(route('blog.store'), adminBlogForm($category, ['slug' => 'forbidden'])),
            'edit' => $this->get(route('blog.edit', $blog)), 'update' => $this->put(route('blog.update', $blog), adminBlogForm($category, ['title' => 'Forbidden'])),
            'destroy' => $this->delete(route('blog.destroy', $blog)), 'status' => $this->put(route('blog.change-status', $blog), ['status' => 0]),
        };
        assertStoreCallerRefused($response, $caller);
        $this->assertDatabaseCount('blogs', 1);
        expect($blog->fresh()->title)->toBe('Fresh recipes')->and($blog->fresh()->status)->toBe(1)->and($blog->fresh()->deleted_at)->toBeNull();
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
