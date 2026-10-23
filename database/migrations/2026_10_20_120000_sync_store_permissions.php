<?php

use App\Support\StorePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Define every store permission the back office enforces and no earlier
     * migration defined. Additive and idempotent (see StorePermissions::sync()):
     * existing rows and every role grant are left alone.
     */
    public function up(): void
    {
        StorePermissions::sync();
    }

    public function down(): void
    {
        // Existing permissions and role grants cannot be told apart from the
        // rows this inserted. Preserve them on rollback.
    }
};
