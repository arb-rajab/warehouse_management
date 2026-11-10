<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('the seeded pages include the home page row the Website > Home settings screen opens', function () {
    $this->seed(PageSeeder::class);

    expect(Page::query()->where('type', 'home_page')->where('slug', 'home')->count())->toBe(1);

    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/website/custom-pages/edit/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Website/Home'));
});

test('seeding the pages twice keeps one row of each type', function () {
    $this->seed(PageSeeder::class);
    $this->seed(PageSeeder::class);

    expect(Page::query()->count())->toBe(6)
        ->and(Page::query()->distinct()->count('type'))->toBe(6);
});

test('the Website > Home settings screen is not found while there is no home page row', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/website/custom-pages/edit/home')->assertNotFound();
});
