<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['add_order', 'edit_order', 'update_order_tracking', 'update_order_notes', 'edit_order_price'] as $name) {
            DB::table(config('permission.table_names.permissions'))->insertOrIgnore(['name' => $name, 'guard_name' => 'store', 'section' => 'sales', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Permission grants belong to the owner; a rollback must not discard them.
    }
};
