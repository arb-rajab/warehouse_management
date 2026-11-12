<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment gateway settings saved on the Payment methods page
     * (App\Support\StorePaymentGateways), one row per gateway and field: the
     * credentials, currency and mode that win over the server's `.env`, and
     * each gateway's `enabled` switch while the admin chooses the enabled list.
     *
     * A store back-office table with a bare name, like admin_audit_logs. It is
     * not a `business_settings` row because that inherited table's `type` is a
     * string(30) and its rows can be read by the public business-settings
     * endpoint; nothing public reads this one. A secret's `value` is the
     * `Crypt::encryptString()` ciphertext (`is_secret`). `updated_by` is the
     * store user's id (`users.id`) with no foreign key on purpose: the row
     * outlives the account.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('field', 40);
            $table->text('value');
            $table->boolean('is_secret')->default(false);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
