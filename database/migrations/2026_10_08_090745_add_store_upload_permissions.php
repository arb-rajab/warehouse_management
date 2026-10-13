<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view_uploads', 'upload_files', 'delete_uploads', 'delete_all_uploads'] as $name) {
            DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
                'name' => $name, 'guard_name' => 'store', 'section' => 'uploads',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Existing permissions and role grants may predate this idempotent migration.
        // Preserve them on rollback.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
