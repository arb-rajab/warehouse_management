<?php

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StoreUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// A browser fixture, run only against Playwright's dedicated SQLite database.
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || basename(config('database.connections.sqlite.database')) !== 'e2e.sqlite') {
    throw new RuntimeException('Refusing to seed order browser fixtures outside the dedicated E2E database.');
}
$admin = StoreUser::query()->where('email', 'store-admin@example.com')->firstOrFail();
foreach ([['Browser Buyer', 'browser-buyer@example.com', 0], ['Browser Representative', 'browser-rep@example.com', 1]] as [$name, $email, $isRep]) {
    StoreUser::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password'])->forceFill(['user_type' => 'customer', 'is_rep' => $isRep, 'rep_discount_percentage' => 10, 'email_verified_at' => now()])->save();
}
$product = Product::query()->orderBy('id')->firstOrFail();
$product->forceFill(['name' => 'Browser Order Product', 'ar_name' => 'منتج اختبار الطلب', 'user_id' => $admin->id, 'published' => 1, 'approved' => 1, 'added_by' => 'admin', 'serial' => 'BROWSER-ORDER', 'unit_price' => 12, 'unit_equal' => 6, 'tax' => 21, 'slug' => 'browser-order-product'])->save();
ProductStock::query()->create(['product_id' => $product->id, 'variant' => '', 'qty' => 50, 'price' => 12, 'sku' => 'BROWSER-ORDER']);
$currencyId = DB::table('currencies')->insertGetId(['name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR']);
DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $currencyId]);
foreach (['Sales_section', 'Staffs_section'] as $section) {
    DB::table('business_settings')->insert(['type' => $section, 'value' => 1]);
}

DB::table('languages')->insertOrIgnore([['name' => 'English', 'code' => 'en', 'rtl' => 0], ['name' => 'Arabic', 'code' => 'ar', 'rtl' => 1]]);
