<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('wms_permissions')->insertOrIgnore(['name' => 'manage_website_scripts', 'guard_name' => 'store', 'section' => 'website_setup', 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'store')->value('id');
        foreach (DB::table('wms_roles')->where('name', 'Super Admin')->where('guard_name', 'store')->pluck('id') as $role) {
            DB::table('wms_role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Remove only this store permission; its assignments cascade through Spatie's FKs.
        DB::table('wms_permissions')->where('name', 'manage_website_scripts')->where('guard_name', 'store')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
