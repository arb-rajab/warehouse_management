<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** Add the abilities without granting any existing staff role new access. */
    public function up(): void
    {
        $sections = [
            'customer' => ['edit_customer'],
            'representatives' => [
                'view_representative_packages',
                'add_representative_package',
                'edit_representative_package',
                'delete_representative_package',
                'assign_representative_package',
                'view_representative_payments',
                'approve_representative_payment',
                'export_representative_payment',
                'view_representative_visits',
                'delete_representative_visit',
            ],
        ];
        foreach ($sections as $section => $names) {
            foreach ($names as $name) {
                DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
                    'name' => $name,
                    'guard_name' => 'store',
                    'section' => $section,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Preserve existing/imported permissions and grants on rollback. */
    public function down(): void {}
};
