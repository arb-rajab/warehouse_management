<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_store_order_submissions', function (Blueprint $table) {
            $table->uuid('submission_key')->primary();
            $table->integer('actor_id');
            $table->integer('order_id');
            $table->char('payload_hash', 64);
            $table->timestamp('created_at');
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('entry_unit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn('entry_unit'));
        Schema::dropIfExists('wms_store_order_submissions');
    }
};
