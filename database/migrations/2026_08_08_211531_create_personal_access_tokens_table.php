<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `wms_` prefixed rather than shared with the store app's Sanctum table:
     * `tokenable_type` stores the model FQN, which is `App\Models\User` in
     * both apps, over two different user tables whose ids overlap. A shared
     * table would let a store token authenticate as the WMS user with the
     * same id. App\Models\PersonalAccessToken points Sanctum at this table
     * (registered in AppServiceProvider). See .ai/rules/shared-database.md.
     */
    public function up(): void
    {
        Schema::create('wms_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * `rename_colliding_tables_to_wms_prefix` runs later, so its `down()`
     * fires before this one during a full rollback and un-prefixes this table
     * back to `personal_access_tokens` wherever this app owns it. Dropping
     * only `wms_personal_access_tokens` would then silently no-op and orphan
     * the legacy-named table, so it is dropped under whichever name it
     * currently holds.
     */
    public function down(): void
    {
        if (Schema::hasTable('wms_personal_access_tokens')) {
            Schema::drop('wms_personal_access_tokens');
        } else {
            Schema::dropIfExists('personal_access_tokens');
        }
    }
};
