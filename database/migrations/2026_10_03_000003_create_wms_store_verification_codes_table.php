<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one live emailed or texted code (password reset, account
     * verification) of a store user, kept as a keyed hash with the moment it
     * stops working and how many times it has been tried.
     *
     * The codes used to sit in clear text in `users.verification_code`, which
     * also held the store's emailed link tokens, never expired and counted no
     * tries. `users` is the store's own table in the shared production
     * database, so the new state lives in a table of this app's.
     *
     * `user_id` is unique: issuing a code replaces the previous one. `issued_at`
     * is what the resend cooldown reads.
     */
    public function up(): void
    {
        Schema::create('wms_store_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unique();
            $table->string('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_store_verification_codes');
    }
};
