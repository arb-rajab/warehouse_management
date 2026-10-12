<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * HandleInertiaRequests shares a guard-aware set of props on the store's
 * pages (.ai/rules/store-backoffice-migration.md §2.1) and leaves the
 * warehouse panel's exactly as they were.
 */
test('a store page shares the store user, narrowly, with their permissions and the sidebar settings', function () {
    $user = actingAsStoreUser(createStoreStaff('view_all_staffs', 'add_staff'));
    DB::table('business_settings')->insert([
        ['type' => 'Staffs_section', 'value' => '1'],
        ['type' => 'Blog_Section', 'value' => '0'],
    ]);

    $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('locale', 'en')
            ->has('auth.user', 3)
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password')
            ->has('store', fn (Assert $store) => $store
                ->where('rtl', false)
                ->where('permissions', ['add_staff', 'view_all_staffs'])
                ->where('isSuperAdmin', false)
                ->where('isRaizerAdmin', false)
                ->has('settings', count(HandleInertiaRequests::STORE_MENU_SETTINGS))
                ->where('settings.Staffs_section', true)
                ->where('settings.Blog_Section', false)
                ->where('settings.Reports_Section', false)
                ->etc())
    );
});

test("a Super Admin is flagged whatever permissions they hold, and so is the vendor's own account", function () {
    $admin = createStoreAdmin(raizerAdmin: true);
    actingAsStoreUser($admin);

    $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('store.permissions', [])
            ->where('store.isSuperAdmin', true)
            ->where('store.isRaizerAdmin', true)
    );
});

test("the store's locale and its direction come from the store's own languages", function () {
    DB::table('languages')->insert([
        ['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1],
        ['code' => 'nl', 'name' => 'Dutch', 'rtl' => 0],
    ]);
    actingAsStoreUser(createStoreStaff('view_all_staffs'));

    $this->withSession(['locale' => 'ar'])->get('/temp/admin/staffs')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('store.rtl', true));

    $this->withSession(['locale' => 'nl'])->get('/temp/admin/staffs')
        ->assertOk()
        ->assertSee('<html lang="nl" dir="ltr">', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'nl')->where('store.rtl', false));
});

test('a warehouse page keeps its own props, with no store ones', function () {
    $user = actingAsAdmin();

    $this->withSession(['locale' => 'ar'])->get('/admin/rows')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->has('auth.user', 3)
            ->where('auth.user.id', $user->id)
            ->missing('store'));
});

test('the sidebar reads only the settings the store pages share, and every one they share', function () {
    preg_match_all("/setting: '([^']+)'/", file_get_contents(resource_path('js/lib/storeAdminMenu.ts')), $matches);

    $readByTheMenu = collect($matches[1])->unique()->sort()->values()->all();
    $shared = collect(HandleInertiaRequests::STORE_MENU_SETTINGS)->sort()->values()->all();

    expect($readByTheMenu)->not->toBeEmpty()->toBe($shared);
});

test('the sidebar settings cost one query, and a key held in several rows reads as its first, as get_setting() does', function () {
    actingAsStoreUser(createStoreStaff('view_all_staffs'));
    DB::table('business_settings')->insert([
        ['type' => 'Blog_Section', 'value' => '1', 'lang' => null],
        ['type' => 'Blog_Section', 'value' => '0', 'lang' => 'ar'],
    ]);
    DB::enableQueryLog();

    $this->get('/temp/admin/staffs')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('store.settings.Blog_Section', get_setting('Blog_Section') == 1)
            ->where('store.settings.Blog_Section', true)
    );

    $settingsQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'business_settings') && str_contains($query['query'], ' in ('));

    expect($settingsQueries)->toHaveCount(1);
});
