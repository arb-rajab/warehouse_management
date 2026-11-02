<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `users.raizer_admin` marked the vendor's own super-admin account. The
     * vendor-only gate is gone and nothing reads the column, so it is dropped.
     * Skipped when the column is absent: a database built from the migrations
     * alone never had it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'raizer_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('raizer_admin');
        });
    }

    /**
     * Reverse the migrations. The values dropped by `up()` are not restored.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'raizer_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('raizer_admin')->nullable();
        });
    }
};
