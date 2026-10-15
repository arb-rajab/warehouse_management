<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sections = [
            'trips' => ['view_all_trips', 'add_trip', 'edit_trip', 'delete_trip'],
            'trucks' => ['view_all_trucks', 'add_truck', 'edit_truck', 'delete_truck'],
            'drivers' => ['view_all_drivers', 'add_driver', 'edit_driver', 'delete_driver'],
            'representatives' => ['view_representative_visits', 'delete_representative_visit'],
            'setup_configurations' => ['google_map_setting'],
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing permission rows and their role grants cannot be distinguished
        // from newly inserted rows. Preserve them on rollback.
    }
};
