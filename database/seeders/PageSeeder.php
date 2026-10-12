<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The 5 legacy storefront policy pages (HomeController::sellerpolicy()/
     * returnpolicy()/supportpolicy()/terms()/privacypolicy()) each look up a
     * Page by `type` and read straight off the result with no null guard --
     * see tests/Feature/LegacyStorefrontRendersTest.php's docblock. This is
     * demo content for local/CI use, not real legal text; production's
     * shared `pages` table already carries the store's actual rows, and
     * DatabaseSeeder refuses to run there at all.
     */
    public function run(): void
    {
        $pages = [
            'terms_conditions_page' => ['title' => 'Terms & Conditions', 'slug' => 'terms-conditions'],
            'privacy_policy_page' => ['title' => 'Privacy Policy', 'slug' => 'privacy-policy'],
            'return_policy_page' => ['title' => 'Return Policy', 'slug' => 'return-policy'],
            'seller_policy_page' => ['title' => 'Seller Policy', 'slug' => 'seller-policy'],
            'support_policy_page' => ['title' => 'Support Policy', 'slug' => 'support-policy'],
        ];

        foreach ($pages as $type => $attributes) {
            // Page declares no $fillable, so firstOrCreate()'s mass
            // assignment trips this app's strict-mode guard in local/
            // testing -- forceCreate() bypasses it the same way
            // Upload::forceCreate() does elsewhere in this codebase.
            if (Page::where('type', $type)->exists()) {
                continue;
            }

            Page::forceCreate([
                'type' => $type,
                'title' => $attributes['title'],
                'slug' => $attributes['slug'],
                'content' => "<p>{$attributes['title']} content goes here.</p>",
                'meta_title' => $attributes['title'],
                'meta_description' => $attributes['title'].' for this store.',
            ]);
        }
    }
}
