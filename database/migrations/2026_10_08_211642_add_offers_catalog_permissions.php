<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view_offers_catalog', 'add_offers_catalog', 'delete_offers_catalog'] as $name) {
            DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'store',
                'section' => 'offers_catalog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Role grants and pre-existing production rows must survive rollback. Revoke through a forward migration.
    }
};
