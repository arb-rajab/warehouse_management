<?php

use App\Models\Blog;
use App\Models\BlogCategory;

function modelBlogCategory(string $name): BlogCategory
{
    return BlogCategory::forceCreate(['category_name' => $name, 'slug' => strtolower($name)]);
}

test('a blog belongs to only its own live category', function () {
    $category = modelBlogCategory('Recipes');
    modelBlogCategory('Other');
    $blog = Blog::forceCreate(['title' => 'Recipe', 'slug' => 'recipe', 'category_id' => $category->id]);
    expect($blog->category->is($category))->toBeTrue();
    $category->delete();
    expect($blog->fresh()->category)->toBeNull();
});

test('blog soft deletion retains stored content and leaves unrelated posts and categories intact', function () {
    $category = modelBlogCategory('Recipes');
    $blog = Blog::forceCreate(['title' => 'Recipe', 'slug' => 'recipe', 'description' => '<p>Recipe content</p>', 'category_id' => $category->id]);
    $other = Blog::forceCreate(['title' => 'Other', 'slug' => 'other', 'category_id' => $category->id]);
    $blog->delete();
    expect(Blog::find($blog->id))->toBeNull()->and(Blog::withTrashed()->find($blog->id)->description)->toBe('<p>Recipe content</p>')
        ->and($other->fresh())->not->toBeNull()->and($category->fresh())->not->toBeNull();
});
