<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The longitude columns the Google Maps pin fills, each decimal(17,15) when
     * created: two digits before the point, so a longitude of 100 or more did
     * not fit. The latitudes stay as they are, since ±90 fits.
     *
     * @var list<array{string, string}>
     */
    private const COLUMNS = [
        ['addresses', 'longitude'],
        ['shops', 'delivery_pickup_longitude'],
    ];

    /**
     * Widens each column that holds fewer than three digits before the point
     * to decimal(18,15). A column already that wide (a rerun, or a database
     * changed by hand) is left as it is, and SQLite, which keeps no decimal
     * precision, has nothing to widen.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            if (Schema::hasColumn($table, $column) && $this->holdsFewerThanThreeIntegerDigits($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->decimal($column, 18, 15)->nullable()->change());
            }
        }
    }

    /**
     * A no-op: narrowing the columns again would fail on, or truncate, every
     * longitude of 100 or more saved since.
     */
    public function down(): void {}

    private function holdsFewerThanThreeIntegerDigits(string $table, string $column): bool
    {
        $type = collect(Schema::getColumns($table))->firstWhere('name', $column)['type'] ?? '';

        if (preg_match('/^(?:decimal|numeric)\((\d+),\s*(\d+)\)/i', (string) $type, $match) !== 1) {
            return false;
        }

        return (int) $match[1] - (int) $match[2] < 3;
    }
};
