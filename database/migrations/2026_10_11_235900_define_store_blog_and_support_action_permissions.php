<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['publish_blog' => 'blog', 'reply_to_support_tickets' => 'support'] as $name => $section) {
            DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
                'name' => $name, 'guard_name' => 'store', 'section' => $section,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Definitions and grants may predate this integration. Preserve both.
    }
};
