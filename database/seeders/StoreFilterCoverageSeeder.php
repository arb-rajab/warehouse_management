<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Run manually; intentionally excluded from DatabaseSeeder. */
class StoreFilterCoverageSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        StoreFilterSeedData::guard();
        DB::transaction(function (): void {
            $this->call([
                StoreAccountFilterSeeder::class,
                StoreCatalogueFilterSeeder::class,
                StoreReferenceFilterSeeder::class,
                StoreActivityFilterSeeder::class,
                StoreEngagementFilterSeeder::class,
            ]);
        });
        Cache::forget('verified_sellers_id');
    }
}
