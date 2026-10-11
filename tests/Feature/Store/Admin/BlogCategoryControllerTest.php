<?php

use App\Models\Blog;
use App\Models\BlogCategory;
use Inertia\Testing\AssertableInertia as Assert;

function marketingBlogCategory(string $name = 'Recipes'): BlogCategory
{
    return BlogCategory::forceCreate(['category_name' => $name, 'slug' => 'Recipes']);
}

test('the blog category list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_blog_categories'));
    $this->get(route('blog-category.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Blog/Categories/Index')
    );
});

test('blog categories return a narrow full payload and search stored Arabic content', function () {
    $category = marketingBlogCategory('وصفات');
    marketingBlogCategory('Unrelated');
    $deleted = marketingBlogCategory('Deleted');
    $deleted->delete();
    actingAsStoreUser(createStoreStaff('view_blog_categories'));
    $this->get(route('blog-category.index', ['search' => 'وصفات']))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('filters', ['per_page' => 20, 'search' => 'وصفات'])
            ->has('categories.data', 1)->has('categories.data.0', fn (Assert $row) => $row
            ->where('id', $category->id)->where('category_name', 'وصفات'))
    );
});

test('blog categories paginate and validate page sizes and search', function () {
    foreach (range(1, 23) as $number) {
        marketingBlogCategory(sprintf('Category %02d', $number));
    }
    actingAsStoreUser(createStoreStaff('view_blog_categories'));
    assertPagesWithoutOverlap(
        fn (int $page) => $this->get(route('blog-category.index', ['page' => $page])),
        fn ($response) => array_column($response->inertiaProps('categories.data'), 'id'), 23, 20
    );
    $this->get(route('blog-category.index', ['per_page' => 10]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('categories.data', 10)->where('filters.per_page', 10)
    );
    $this->getJson(route('blog-category.index', ['per_page' => 7]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson(route('blog-category.index', ['search' => str_repeat('a', 256)]))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('the add blog category form can be rendered', function () {
    actingAsStoreUser(createStoreStaff('add_blog_category'));
    $this->get(route('blog-category.create'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Blog/Categories/Create')
    );
});

test('the edit blog category form can be rendered with its full payload', function () {
    $category = marketingBlogCategory();
    actingAsStoreUser(createStoreStaff('edit_blog_category'));
    $this->get(route('blog-category.edit', $category))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Blog/Categories/Edit')
            ->has('category', fn (Assert $row) => $row->where('id', $category->id)->where('category_name', 'Recipes'))
    );
});

test('a blog category is created preserving legacy slug generation and database content', function () {
    actingAsStoreUser(createStoreStaff('add_blog_category'));
    $this->post(route('blog-category.store'), ['category_name' => 'Fresh Recipes!', 'slug' => 'forged'])
        ->assertRedirect(route('blog-category.index'));
    $this->assertDatabaseHas('blog_categories', ['category_name' => 'Fresh Recipes!', 'slug' => 'Fresh-Recipes']);
});

test('updating a blog category validates its name and preserves other records', function () {
    $category = marketingBlogCategory();
    $other = marketingBlogCategory('Other');
    actingAsStoreUser(createStoreStaff('edit_blog_category'));
    $this->put(route('blog-category.update', $category), ['category_name' => 'New Recipes'])
        ->assertRedirect(route('blog-category.index'));
    expect($category->fresh()->slug)->toBe('New-Recipes')->and($other->fresh()->category_name)->toBe('Other');
});

test('blog category writes validate a required string within the database width', function (string $action, mixed $name) {
    $category = marketingBlogCategory();
    actingAsStoreUser(createStoreStaff('add_blog_category', 'edit_blog_category'));
    $response = $action === 'store'
        ? $this->postJson(route('blog-category.store'), ['category_name' => $name])
        : $this->putJson(route('blog-category.update', $category), ['category_name' => $name]);
    $response->assertUnprocessable()->assertJsonValidationErrors('category_name');
    $this->assertDatabaseCount('blog_categories', 1);
    expect($category->fresh()->category_name)->toBe('Recipes');
})->with(['store', 'update'])->with(['empty' => '', 'array' => [['name']], 'too long' => str_repeat('a', 256)]);

test('deleting a blog category soft deletes only that category and retains its blog content', function () {
    $category = marketingBlogCategory();
    $other = marketingBlogCategory('Other');
    $blog = Blog::forceCreate(['category_id' => $category->id, 'title' => 'Recipe', 'slug' => 'recipe']);
    actingAsStoreUser(createStoreStaff('delete_blog_category'));
    $this->delete(route('blog-category.destroy', [$category, 'page' => 2, 'search' => 'Recipes']))
        ->assertRedirect(route('blog-category.index', ['page' => 2, 'search' => 'Recipes']));
    $this->assertSoftDeleted($category);
    expect($other->fresh())->not->toBeNull();
    $this->assertDatabaseHas('blogs', ['id' => $blog->id, 'slug' => 'recipe']);
});

test('missing blog categories return 404 for edit update and destroy', function (string $action) {
    actingAsStoreUser(createStoreStaff('edit_blog_category', 'delete_blog_category'));
    $response = match ($action) {
        'edit' => $this->get(route('blog-category.edit', 999999)),
        'update' => $this->put(route('blog-category.update', 999999), ['category_name' => 'Missing']),
        'destroy' => $this->delete(route('blog-category.destroy', 999999)),
    };
    $response->assertNotFound();
})->with(['edit', 'update', 'destroy']);

foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
    test("blog category {$action} refuses unauthorized callers and leaves the database unchanged", function (string $caller) use ($action) {
        $category = marketingBlogCategory();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'index' => $this->get(route('blog-category.index')),
            'create' => $this->get(route('blog-category.create')),
            'store' => $this->post(route('blog-category.store'), ['category_name' => 'Forbidden']),
            'edit' => $this->get(route('blog-category.edit', $category)),
            'update' => $this->put(route('blog-category.update', $category), ['category_name' => 'Forbidden']),
            'destroy' => $this->delete(route('blog-category.destroy', $category)),
        };
        assertStoreCallerRefused($response, $caller);
        $this->assertDatabaseCount('blog_categories', 1);
        expect($category->fresh()->category_name)->toBe('Recipes');
    })->with('refused store admin callers');
}
