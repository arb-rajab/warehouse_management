<?php

use App\Support\StorePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Define `product_field_sources`, the gate of Setup > Product data
     * sources. Additive and idempotent (StorePermissions::sync()): no role is
     * granted it, so until the owner grants it only Super Admin passes.
     */
    public function up(): void
    {
        StorePermissions::sync();
    }

    public function down(): void
    {
        // A permission row cannot be told apart from one an earlier sync or
        // an operator created, and roles may hold it by now. Preserve it.
    }
};
