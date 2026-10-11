<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_delete')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_delete')->nullable();
            });
        }
    }

    /** Legacy archive flags must survive rollback; this additive migration owns no production data. */
    public function down(): void {}
};
