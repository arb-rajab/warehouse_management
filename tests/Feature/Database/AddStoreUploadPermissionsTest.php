<?php

use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as SpatiePermission;

test('upload permissions are store-only and idempotent, and rollback preserves WMS rows', function () {
    $web = SpatiePermission::findOrCreate('view_uploads', 'web');
    $migration = require collect(glob(database_path('migrations/*add_store_upload_permissions.php')))->sole();
    $migration->up();
    expect(Permission::query()->whereIn('name', ['view_uploads', 'upload_files', 'delete_uploads'])->count())->toBe(3)
        ->and(Permission::query()->where('name', 'view_uploads')->first()->section)->toBe('uploads');
    $migration->down();
    expect(Permission::query()->whereIn('name', ['view_uploads', 'upload_files', 'delete_uploads'])->count())->toBe(0)
        ->and(DB::table(config('permission.table_names.permissions'))->where('id', $web->id)->exists())->toBeTrue();
});
