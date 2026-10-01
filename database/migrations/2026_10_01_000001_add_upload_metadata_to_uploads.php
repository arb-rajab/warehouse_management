<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns the store's upload flows write besides the ones this
     * app's own stand-in `uploads` carries (see
     * `add_user_id_to_uploads` for why the stand-in stays small).
     * Base64ImageUploadService, the mobile API's image upload, records the
     * client's display name, the extension, the type and the size on every
     * upload, so the stand-in needs them for that flow to run (and be tested)
     * here. Types match the store's own table.
     *
     * Each column is guarded: in production `uploads` is the store's table and
     * already has them, so this is a no-op there -- this app must never alter
     * a table it only reads.
     */
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('uploads', 'file_original_name')) {
                $table->string('file_original_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('uploads', 'extension')) {
                $table->string('extension', 10)->nullable();
            }

            if (! Schema::hasColumn('uploads', 'type')) {
                $table->string('type', 15)->nullable();
            }

            if (! Schema::hasColumn('uploads', 'file_size')) {
                $table->unsignedInteger('file_size')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Guarded on the environment rather than on `hasColumn()`, which cannot
     * tell the store's `uploads` from a local stand-in -- a rollback must
     * drop the development copy's columns and never the store's own.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        Schema::table('uploads', function (Blueprint $table) {
            foreach (['file_original_name', 'extension', 'type', 'file_size'] as $column) {
                if (Schema::hasColumn('uploads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
