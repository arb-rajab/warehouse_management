<?php

namespace Tests\Fixtures;

use App\Models\Brand;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Database\Seeder;

/** Stable store products for real campaign and popup browser form submissions. */
class StoreCampaignBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \RuntimeException('Campaign browser fixtures require the testing environment.');
        }
        $admin = StoreUser::query()->where('email', 'store-admin@example.com')->firstOrFail();
        $brand = Brand::forceCreate(['name' => 'Campaign browser brand', 'slug' => 'campaign-browser-brand']);
        foreach (['base', 'reward'] as $kind) {
            Product::factory()->create([
                'name' => 'Campaign browser '.$kind,
                'slug' => 'campaign-browser-'.$kind,
                'user_id' => $admin->id,
                'brand_id' => $brand->id,
                'published' => 1,
                'approved' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'discount_type' => 'amount',
            ]);
        }
    }
}
