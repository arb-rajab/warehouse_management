<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The storefront's SSLCommerz, AamarPay and iyzico payments now start as a
     * pending row too, like the mobile API's: their return is a cross-site POST
     * that carries no session cookie (SESSION_SAME_SITE=lax), so the payment is
     * found by the reference sent to the gateway instead of by the session.
     *
     * - `gateway_amount`/`gateway_currency`: the converted amount charged and its
     *   currency, fixed at the start so the return compares the gateway's figure
     *   with it to the cent, whatever the rate does in between.
     * - `expires_at`: a reference older than this is refused. Null (the API's
     *   rows and every row before this migration) never expires.
     *
     * The index serves the daily prune of expired pending rows.
     */
    public function up(): void
    {
        Schema::table('wms_gateway_payments', function (Blueprint $table) {
            $table->decimal('gateway_amount', 20, 2)->nullable()->after('amount');
            $table->string('gateway_currency', 3)->nullable()->after('gateway_amount');
            $table->timestamp('expires_at')->nullable()->after('completed_at');

            $table->index(['completed_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wms_gateway_payments', function (Blueprint $table) {
            $table->dropIndex(['completed_at', 'expires_at']);
            $table->dropColumn(['gateway_amount', 'gateway_currency', 'expires_at']);
        });
    }
};
