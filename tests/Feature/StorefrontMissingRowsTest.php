<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\DB;

/**
 * A blog post or policy page whose row was missing reached its view as null, so
 * the whole page was a 500 instead of a 404. An unpublished blog post (status 0)
 * could also be opened by its slug although the sitemap leaves it out: both now
 * answer 404. Custom pages already did; a policy or custom page has no
 * status column, so there is no published flag to check for them.
 */
beforeEach(function () {
    seedStoreCurrency();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function missingRowsBlog(array $attributes = []): void
{
    DB::table('blogs')->insert([
        'category_id' => 1,
        'title' => 'Opening hours',
        'slug' => 'opening-hours',
        'description' => '<p>We open at nine.</p>',
        'meta_title' => 'Opening hours',
        'status' => 1,
        ...$attributes,
    ]);
}

test('a published blog post renders with its content', function () {
    missingRowsBlog();

    $this->get('/temp/blog/opening-hours')
        ->assertOk()
        ->assertSee('Opening hours')
        ->assertSee('We open at nine.', false);
});

test('a blog post that does not exist answers 404', function () {
    missingRowsBlog();

    $this->get('/temp/blog/no-such-post')->assertNotFound();
});

test('an unpublished blog post answers 404 by its slug', function () {
    missingRowsBlog(['status' => 0]);

    $this->get('/temp/blog/opening-hours')->assertNotFound();
});

test('a soft-deleted blog post answers 404 by its slug', function () {
    missingRowsBlog(['deleted_at' => now()]);

    $this->get('/temp/blog/opening-hours')->assertNotFound();
});

test('a policy page renders when its row exists and answers 404 when it does not', function (string $uri, string $type) {
    $this->get($uri)->assertNotFound();

    $this->seed(PageSeeder::class);
    $this->get($uri)->assertOk();

    Page::where('type', $type)->delete();
    $this->get($uri)->assertNotFound();
})->with([
    'seller policy' => ['/temp/seller-policy', 'seller_policy_page'],
    'return policy' => ['/temp/return-policy', 'return_policy_page'],
    'support policy' => ['/temp/support-policy', 'support_policy_page'],
    'terms' => ['/temp/terms', 'terms_conditions_page'],
    'privacy policy' => ['/temp/privacy-policy', 'privacy_policy_page'],
]);

test('only the missing policy page answers 404 while the others still render', function () {
    $this->seed(PageSeeder::class);
    Page::where('type', 'terms_conditions_page')->delete();

    $this->get('/temp/terms')->assertNotFound();
    $this->get('/temp/privacy-policy')->assertOk();
});

test('a custom page renders when it exists and answers 404 when it does not', function () {
    Page::forceCreate(['type' => 'custom_page', 'title' => 'About', 'slug' => 'about-us', 'content' => '<p>x</p>']);

    $this->get('/temp/about-us')->assertOk();
    $this->get('/temp/no-such-page')->assertNotFound();
    $this->get('/temp/mobile-page/no-such-page')->assertNotFound();
});
