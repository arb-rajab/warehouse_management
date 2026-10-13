<?php

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\FlashDeal;

test('public blog pages retain Blade rendering, published filtering, canonical metadata and schema markup', function () {
    $category = BlogCategory::forceCreate(['category_name' => 'وصفات', 'slug' => 'recipes']);
    $blog = Blog::forceCreate([
        'category_id' => $category->id, 'title' => 'Public recipe', 'slug' => 'public-recipe',
        'description' => '<p>Public content</p><script>alert(1)</script>', 'status' => 1,
        'meta_title' => 'Recipe metadata', 'meta_description' => 'Recipe search description',
        'meta_keywords' => 'recipes',
    ]);
    Blog::forceCreate(['category_id' => $category->id, 'title' => 'Draft recipe', 'slug' => 'draft-recipe', 'status' => 0]);

    $this->get(route('blog'))->assertOk()->assertViewIs('shop.frontend.blog.listing')
        ->assertSee('Public recipe')->assertDontSee('Draft recipe');
    $this->get(route('blog.details', $blog->slug))->assertOk()->assertViewIs('shop.frontend.blog.details')
        ->assertSee('<p>Public content</p>', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('وصفات')->assertSee('Recipe metadata')->assertSee('Recipe search description')
        ->assertSee('<meta property="og:url" content="'.route('blog.details', 'public-recipe').'"', false)
        ->assertSee('<meta itemprop="name" content="Recipe metadata">', false);
    $this->withHeader('X-Inertia', 'true')->get(route('blog.details', $blog->slug))
        ->assertConflict()->assertHeader('X-Inertia-Location', route('blog.details', $blog->slug));
});

test('public flash campaigns retain their server rendered dates and canonical route', function () {
    $deal = FlashDeal::forceCreate([
        'title' => 'Public campaign', 'slug' => 'public-campaign', 'status' => 1,
        'start_date' => strtotime('2026-10-01'), 'end_date' => strtotime('2030-10-30 16:45:00'),
        'text_color' => 'dark',
    ]);
    expect(route('flash-deal-details', $deal->slug, false))->toBe('/temp/flash-deal/public-campaign');
    $this->get(route('flash-deal-details', $deal->slug))->assertOk()->assertViewIs('shop.frontend.flash_deal_details')
        ->assertSee('Public campaign')->assertSee('end-date="2030/10/30 16:45:00"', false);
    $this->withHeader('X-Inertia', 'true')->get(route('flash-deal-details', $deal->slug))
        ->assertConflict()->assertHeader('X-Inertia-Location', route('flash-deal-details', $deal->slug));
});
