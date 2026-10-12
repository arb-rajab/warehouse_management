<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Singleton app-wide settings — at most one row ever exists (see
     * App\Models\Setting::current()), so there is no natural business key to
     * use as the primary key; a plain auto-increment id is used like every
     * other WMS-owned table, and the "only one row" invariant is kept in
     * application code (firstOrNew() with no conditions, mirroring
     * MobileAppVersionRequirement/SetMinimumAppVersionCommand) rather than a
     * DB constraint.
     *
     * qr_code_width/qr_code_height replace the hardcoded QR sizes every
     * QR-label export used to use — see BuildsQrLabels. They describe the
     * label's total box (QR plus any text below it), not just the QR square,
     * so the height default carries real headroom beyond the width — see
     * App\Models\Setting::DEFAULT_QR_CODE_HEIGHT.
     */
    public function up(): void
    {
        Schema::create('wms_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('qr_code_width')->default(850);
            $table->unsignedSmallInteger('qr_code_height')->default(1000);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wms_settings');
    }
};
