<?php

use App\Support\CustomerTaxNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use stdClass;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A customer's tax number is their business identifier, so `users` gets a
     * unique index on it (NULL stays allowed, many times over). Nothing kept
     * it unique before: the Otajer member import stored the feed's value raw,
     * self-registration and the profile forms never checked it, and
     * `fix:members` bans duplicate accounts without clearing them. So, first:
     *
     * - every value is trimmed, and a blank one becomes NULL;
     * - each tax number more than one account holds stays on the account
     *   CustomerTaxNumber::duplicateGroups() ranks first (not banned or
     *   archived, then linked to Otajer, then the lowest id). Every other
     *   account's value is written to `customer_tax_number_releases` (who had
     *   it, who kept it) and only then set to NULL. Nothing is deleted;
     *   `store:tax-number-duplicates` previews this beforehand and
     *   `--released` lists the record afterwards.
     *
     * Grouping happens in SQL, so on MySQL it follows the column's collation
     * exactly as the index will ("nl123" and "NL123" are one group).
     *
     * No `hasTable()`/environment guard: production's `users` is exactly where
     * this has to run. An index that already exists is left alone.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'tax_number') || Schema::hasIndex('users', ['tax_number'], 'unique')) {
            return;
        }

        if (! Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE)) {
            Schema::create(CustomerTaxNumber::RELEASES_TABLE, function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('kept_user_id');
                $table->string('tax_number');
                $table->timestamp('released_at')->nullable();
            });
        }

        $this->normalizeValues();
        $this->releaseDuplicates();

        Schema::table('users', function (Blueprint $table) {
            $table->unique('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the index and gives each released tax number back to its account
     * while that account still has none. The record is kept in production,
     * where it may be the only copy of a value an account has since replaced.
     * The trimming is not undone.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'tax_number') && Schema::hasIndex('users', ['tax_number'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['tax_number']);
            });
        }

        if (! Schema::hasTable(CustomerTaxNumber::RELEASES_TABLE)) {
            return;
        }

        DB::table(CustomerTaxNumber::RELEASES_TABLE)->orderBy('id')->each(function (stdClass $release): void {
            DB::table('users')->where('id', $release->user_id)->whereNull('tax_number')->update(['tax_number' => $release->tax_number]);
        });

        if (! app()->isProduction()) {
            Schema::drop(CustomerTaxNumber::RELEASES_TABLE);
        }
    }

    private function normalizeValues(): void
    {
        DB::table('users')->select(['id', 'tax_number'])->whereNotNull('tax_number')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $normalized = CustomerTaxNumber::normalize($row->tax_number);

                if ($normalized !== $row->tax_number) {
                    DB::table('users')->where('id', $row->id)->update(['tax_number' => $normalized]);
                }
            }
        });
    }

    private function releaseDuplicates(): void
    {
        $released = 0;

        DB::transaction(function () use (&$released): void {
            foreach (CustomerTaxNumber::duplicateGroups() as $group) {
                $kept = $group['customers'][0];

                foreach (array_slice($group['customers'], 1) as $customer) {
                    $value = DB::table('users')->where('id', $customer->id)->value('tax_number');

                    DB::table(CustomerTaxNumber::RELEASES_TABLE)->insert([
                        'user_id' => $customer->id,
                        'kept_user_id' => $kept->id,
                        'tax_number' => $value,
                        'released_at' => now(),
                    ]);
                    DB::table('users')->where('id', $customer->id)->update(['tax_number' => null]);
                    $released++;
                }
            }
        });

        if ($released > 0) {
            Log::warning("Unique tax numbers: released the tax number of {$released} duplicate customer account(s); see `php artisan store:tax-number-duplicates --released`.");
        }
    }
};
