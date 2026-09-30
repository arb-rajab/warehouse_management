<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateUserPageViewsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000013_create_user_page_views_table.php');
}

test('the migration leaves an existing user_page_views table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('user_page_views');
    Schema::create('user_page_views', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateUserPageViewsTableMigration()->up();

    expect(Schema::hasColumn('user_page_views', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('user_page_views', 'user_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('user_page_views', [
        'id', 'user_id', 'viewable_type', 'viewable_id', 'viewed_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test("deleting a user deletes their page views, and no one else's", function () {
    $userId = DB::table('users')->insertGetId(['name' => 'Viewer']);
    $otherUserId = DB::table('users')->insertGetId(['name' => 'Other Viewer']);

    foreach ([$userId, $otherUserId] as $viewerId) {
        DB::table('user_page_views')->insert([
            'user_id' => $viewerId, 'viewable_type' => 'product', 'viewable_id' => 1, 'viewed_at' => now(),
        ]);
    }

    DB::table('users')->where('id', $userId)->delete();

    expect(DB::table('user_page_views')->pluck('user_id')->all())->toBe([$otherUserId]);
});

test('the migration skips dropping the user_page_views table in production', function () {
    app()->instance('env', 'production');
    loadCreateUserPageViewsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('user_page_views'))->toBeTrue();
});
