<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is the legacy store's OWN separate `users` table — deliberately
     * never merged with this app's `wms_users`; both stay, permanently, by
     * design (see .ai/rules/legacy-albaraka-holland.md §2/§3), since they hold
     * two entirely different populations (store customers/sellers/staff vs.
     * WMS warehouse staff).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->integer('referred_by')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_id', 50)->nullable();
            $table->text('refresh_token')->nullable();
            $table->longText('access_token')->nullable();
            $table->string('user_type', 20)->default('customer');
            $table->string('name', 191);
            $table->string('email', 191)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('verification_code')->nullable();
            $table->text('new_email_verificiation_code')->nullable();
            $table->string('password', 191)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('device_token')->nullable();
            $table->string('avatar', 256)->nullable();
            $table->string('avatar_original', 256)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('country', 30)->nullable();
            $table->string('state', 30)->nullable();
            $table->string('city', 30)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->double('balance', 20, 2)->default('0.00');
            $table->tinyInteger('banned')->default(0);
            $table->string('referral_code')->nullable();
            $table->integer('customer_package_id')->nullable();
            $table->integer('remaining_uploads')->nullable()->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
