<?php

use App\Http\Controllers\Store\Admin\RoleController;
use App\Models\Permission;
use App\Models\Role;
use App\Support\StorePermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Every permission name the back office enforces, read from the code that
 * enforces it (not from the audit JSON): route middleware strings in
 * routes/shop-admin.php, the `Middleware('permission:…')` entries and inline
 * checks of the Store controllers, the settings page map, and
 * storeAdminMenu.ts. A name found here that StorePermissions lacks is a gate
 * a fresh database cannot offer in the role form or grant.
 *
 * @return array<string, list<string>> permission name => the sources that enforce it
 */
function enforcedStorePermissionNames(): array
{
    $found = [];
    $note = function (string $name, string $source) use (&$found): void {
        $found[$name][] = $source;
    };
    $middlewareStrings = function (string $source, string $label) use ($note): void {
        preg_match_all('/[\'"]permission:([^\'"]+)[\'"]/', $source, $matches);
        foreach ($matches[1] as $names) {
            foreach (explode('|', $names) as $name) {
                $note($name, $label);
            }
        }
    };

    $middlewareStrings(File::get(base_path('routes/shop-admin.php')), 'routes/shop-admin.php');

    foreach (File::allFiles(app_path('Http/Controllers/Store')) as $file) {
        $source = $file->getContents();
        $label = 'Store/'.$file->getRelativePathname();
        $middlewareStrings($source, $label);

        // The settings page map: 'permission' => 'smtp_settings'.
        preg_match_all('/[\'"]permission[\'"]\s*=>\s*[\'"]([a-z][a-z0-9_&]*)[\'"]/', $source, $map);
        // Inline checks: ->can('x'), Gate::authorize('x'). Policy abilities (viewAny, delete) have no underscore.
        preg_match_all('/(?:->can|Gate::authorize|Gate::allows)\(\s*[\'"]([a-z]+(?:_[a-z&]+)+)[\'"]/', $source, $inline);
        // A `$permission = match (…) { … => 'x' }` selector (OrderController::all_orders).
        $arms = [];
        if (preg_match_all('/\$permission\s*=\s*match\b.*?\};/s', $source, $blocks) > 0) {
            preg_match_all('/=>\s*[\'"]([a-z]+(?:_[a-z&]+)+)[\'"]/', implode("\n", $blocks[0]), $matched);
            $arms = $matched[1];
        }
        foreach ([...$map[1], ...$inline[1], ...$arms] as $name) {
            $note($name, $label);
        }
    }

    // storeAdminMenu.ts: `permission: 'x'` or `permission: ['x', 'y']`.
    $menu = File::get(resource_path('js/lib/storeAdminMenu.ts'));
    preg_match_all('/permission:\s*(\[[^\]]*\]|\'[^\']+\')/', $menu, $entries);
    foreach ($entries[1] as $entry) {
        preg_match_all('/\'([^\']+)\'/', $entry, $names);
        foreach ($names[1] as $name) {
            $note($name, 'storeAdminMenu.ts');
        }
    }

    return $found;
}

test('every permission the back office enforces is defined in StorePermissions', function () {
    $enforced = enforcedStorePermissionNames();
    $sources = collect($enforced)->flatten()->map(fn (string $source) => str_starts_with($source, 'Store/') ? 'controllers' : $source)->unique()->sort()->values()->all();

    // A scan that quietly stopped matching would pass the check below.
    expect($sources)->toBe(['controllers', 'routes/shop-admin.php', 'storeAdminMenu.ts'])
        ->and(count($enforced))->toBeGreaterThan(150);

    $missing = collect($enforced)->except(array_keys(StorePermissions::all()))
        ->map(fn (array $sources, string $name) => $name.' ('.implode(', ', array_unique($sources)).')')
        ->values()->all();

    expect($missing)->toBe([], 'Add these to app/Support/StorePermissions.php, in the section the role form groups their module under: '.implode(', ', $missing));
});

test('the permission list names each permission once, in a short section, and keeps the removed addons out', function () {
    $sections = (new ReflectionClass(StorePermissions::class))->getReflectionConstant('SECTIONS')->getValue();
    $names = collect($sections)->flatten();
    $removedAddons = (new ReflectionClass(RoleController::class))
        ->getReflectionConstant('REMOVED_ADDON_SECTIONS')->getValue();

    expect($names->duplicates()->all())->toBe([])
        ->and($names->count())->toBe(count(StorePermissions::all()))
        ->and(collect($sections)->keys()->filter(fn (string $section) => strlen($section) > 50)->all())->toBe([])
        ->and(array_intersect(array_keys($sections), $removedAddons))->toBe([]);
});

test('a migrated database holds exactly the listed permissions, with the sections the earlier migrations assigned', function () {
    $rows = Permission::query()->pluck('section', 'name')->all();

    expect($rows)->toEqual(StorePermissions::all());
});

test('the sync creates only the missing permissions, once, and never touches existing rows, roles or the web guard', function () {
    $all = StorePermissions::all();
    $web = DB::table('wms_permissions')->insertGetId([
        'name' => 'view_all_orders', 'guard_name' => 'web', 'section' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $role = Role::findOrCreate('Kept clerk', 'store');
    $role->givePermissionTo('view_all_orders');
    Permission::query()->where('name', 'view_all_orders')->update(['section' => 'kept_section']);
    Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->delete();
    $grantsBefore = DB::table('wms_role_has_permissions')->get();
    $webBefore = DB::table('wms_permissions')->where('guard_name', 'web')->get();
    $storeBefore = DB::table('wms_permissions')->where('guard_name', 'store')->orderBy('id')->get();

    $first = StorePermissions::sync();
    $second = StorePermissions::sync();

    expect(collect($first['created'])->sort()->values()->all())->toBe(['add_coupon', 'view_blogs'])
        ->and(count($first['existing']))->toBe(count($all) - 2)
        ->and($second['created'])->toBe([])
        ->and(count($second['existing']))->toBe(count($all))
        ->and(Permission::query()->where('name', 'view_all_orders')->value('section'))->toBe('kept_section')
        ->and(Permission::query()->where('name', 'add_coupon')->value('section'))->toBe($all['add_coupon'])
        ->and(DB::table('wms_role_has_permissions')->get())->toEqual($grantsBefore)
        ->and(DB::table('wms_model_has_permissions')->count())->toBe(0)
        ->and(DB::table('wms_permissions')->where('guard_name', 'web')->get())->toEqual($webBefore)
        ->and(DB::table('wms_permissions')->where('id', $web)->value('section'))->toBeNull()
        ->and(DB::table('wms_permissions')->where('guard_name', 'store')->whereIn('id', $storeBefore->pluck('id'))->orderBy('id')->get()->map(fn ($row) => collect($row)->except('section', 'updated_at')->all())->all())
        ->toEqual($storeBefore->map(fn ($row) => collect($row)->except('section', 'updated_at')->all())->all());
});

test('the sync gives a database with no store permissions every one of them and grants none', function () {
    DB::table('wms_permissions')->where('guard_name', 'store')->delete();

    $result = StorePermissions::sync();

    expect(count($result['created']))->toBe(count(StorePermissions::all()))
        ->and($result['existing'])->toBe([])
        ->and(Permission::query()->pluck('section', 'name')->all())->toEqual(StorePermissions::all())
        ->and(DB::table('wms_permissions')->where('guard_name', 'store')->whereNull('created_at')->count())->toBe(0)
        ->and(DB::table('wms_role_has_permissions')->count())->toBe(0)
        ->and(DB::table('wms_model_has_permissions')->count())->toBe(0);
});

test('a dry run writes nothing and leaves the permission cache alone, a real run flushes it', function () {
    Permission::query()->whereIn('name', ['add_coupon', 'view_blogs'])->delete();
    $before = DB::table('wms_permissions')->orderBy('id')->get();
    $cache = Cache::store(config('permission.cache.store') === 'default' ? null : config('permission.cache.store'));
    $cache->put(config('permission.cache.key'), 'warm');

    $dry = StorePermissions::sync(dryRun: true);

    expect(collect($dry['created'])->sort()->values()->all())->toBe(['add_coupon', 'view_blogs'])
        ->and(DB::table('wms_permissions')->orderBy('id')->get())->toEqual($before)
        ->and($cache->has(config('permission.cache.key')))->toBeTrue();

    StorePermissions::sync();

    expect($cache->has(config('permission.cache.key')))->toBeFalse()
        ->and(DB::table('wms_permissions')->count())->toBe($before->count() + 2);
});
