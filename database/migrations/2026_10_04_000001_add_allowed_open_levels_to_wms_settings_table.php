<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `allowed_open_levels` is the JSON list of cell levels (`cells.flat_number`)
     * on which a pallet may be opened. Nullable with no stored default: a NULL
     * (a row from before this column existed, or a freshly created one) reads
     * back as `Setting::DEFAULT_ALLOWED_OPEN_LEVELS` through the model's
     * accessor, which also sidesteps MySQL's rule against literal defaults on
     * JSON columns.
     */
    public function up(): void
    {
        Schema::table('wms_settings', function (Blueprint $table) {
            $table->json('allowed_open_levels')->nullable()->after('qr_code_height');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Lossy: dropping the column discards the configured levels.
     */
    public function down(): void
    {
        Schema::table('wms_settings', function (Blueprint $table) {
            $table->dropColumn('allowed_open_levels');
        });
    }
};
