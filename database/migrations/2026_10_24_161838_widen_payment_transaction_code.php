<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Match the payout request's existing 191-character contract without
     * narrowing a legacy column that is already wider. SQLite has no width.
     */
    public function up(): void
    {
        $column = collect(Schema::getColumns('payments'))->firstWhere('name', 'txn_code');
        if (preg_match('/^varchar\((\d+)\)$/i', (string) ($column['type'] ?? ''), $match) === 1 && (int) $match[1] < 191) {
            Schema::table('payments', fn (Blueprint $table) => $table->string('txn_code', 191)->nullable()->change());
        }
    }

    /**
     * Never truncate transaction references written after the widening.
     */
    public function down(): void {}
};
