<?php

use App\Support\ReferenceData;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Gives a fresh database the countries, states, currencies, languages and
     * CMS pages the legacy store carried (see App\Support\ReferenceData). It
     * only inserts what is missing, so it is safe on a database that already
     * has the legacy rows.
     *
     * Skipped under APP_ENV=testing: the test suites and the browser fixtures
     * start from empty reference tables and create the rows they need;
     * ReferenceDataTest loads the data explicitly.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        (new ReferenceData)->load();
    }

    /**
     * Reference rows may have been edited or referenced since (addresses point
     * at countries), so rolling back leaves them in place.
     */
    public function down(): void
    {
        //
    }
};
