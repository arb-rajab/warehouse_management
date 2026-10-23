<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Owner confirmed on 2026-10-23 that every existing product is from
        // Otajer. This records classification; the next sync refreshes the feed.
        DB::table('products')->whereNull('synced_at')->update(['synced_at' => now()]);
    }

    public function down(): void
    {
        // Classification cannot safely be undone after subsequent syncs.
        // Any correction needs an explicitly reviewed forward migration.
    }
};
