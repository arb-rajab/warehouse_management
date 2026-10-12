<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The seller API's free-package call checked that one package id was free
 * (`seller_package_id`) and granted another (`package_id`): a seller named a free
 * package for the check and a paid one to receive, and got the paid package
 * (its upload limit and duration) for nothing.
 */
function createSellerPackageTables(): void
{
    Schema::create('seller_packages', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->double('amount')->default(0);
        $table->integer('product_upload_limit')->default(0);
        $table->integer('duration')->default(0);
        $table->timestamps();
    });
    Schema::create('seller_package_payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('seller_package_id');
        $table->string('payment_method')->nullable();
        $table->text('payment_details')->nullable();
        $table->tinyInteger('approval')->default(0);
        $table->tinyInteger('offline_payment')->default(0);
        $table->string('reciept')->nullable();
        $table->timestamps();
    });
}

test('a free package cannot be used to receive a paid one', function () {
    createSellerPackageTables();
    $seller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop-1']);
    $freeId = DB::table('seller_packages')->insertGetId(['name' => 'Free', 'amount' => 0, 'product_upload_limit' => 5, 'duration' => 30]);
    $paidId = DB::table('seller_packages')->insertGetId(['name' => 'Gold', 'amount' => 500, 'product_upload_limit' => 500, 'duration' => 365]);

    $this->withToken(storeApiToken($seller))
        ->postJson('/temp/api/v2/seller/seller-package/free-package', ['seller_package_id' => $freeId, 'package_id' => $paidId])
        ->assertOk()
        ->assertJsonPath('result', false);

    expect(DB::table('shops')->where('id', $shopId)->value('seller_package_id'))->toBeNull()
        ->and((int) DB::table('shops')->where('id', $shopId)->value('product_upload_limit'))->toBe(0)
        ->and(DB::table('seller_package_payments')->count())->toBe(0);
});

test('a free package is granted when it is the one named', function () {
    createSellerPackageTables();
    $seller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop-1']);
    $freeId = DB::table('seller_packages')->insertGetId(['name' => 'Free', 'amount' => 0, 'product_upload_limit' => 5, 'duration' => 30]);

    $this->withToken(storeApiToken($seller))
        ->postJson('/temp/api/v2/seller/seller-package/free-package', ['package_id' => $freeId])
        ->assertOk()
        ->assertJsonPath('result', true);

    expect((int) DB::table('shops')->where('id', $shopId)->value('seller_package_id'))->toBe($freeId)
        ->and((int) DB::table('shops')->where('id', $shopId)->value('product_upload_limit'))->toBe(5)
        ->and(DB::table('seller_package_payments')->where('user_id', $seller->id)->count())->toBe(1);
});

test('a paid package is not granted through the free-package call', function () {
    createSellerPackageTables();
    $seller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop-1']);
    $paidId = DB::table('seller_packages')->insertGetId(['name' => 'Gold', 'amount' => 500, 'product_upload_limit' => 500, 'duration' => 365]);

    $this->withToken(storeApiToken($seller))
        ->postJson('/temp/api/v2/seller/seller-package/free-package', ['package_id' => $paidId])
        ->assertJsonPath('result', false);

    expect(DB::table('shops')->where('id', $shopId)->value('seller_package_id'))->toBeNull();
});

test('an offline package payment is validated and cannot be submitted twice while pending', function () {
    createSellerPackageTables();
    $seller = createStoreUser('seller');
    DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop-1']);
    $packageId = DB::table('seller_packages')->insertGetId(['name' => 'Gold', 'amount' => 500, 'product_upload_limit' => 500, 'duration' => 365]);
    $otherPackageId = DB::table('seller_packages')->insertGetId(['name' => 'Silver', 'amount' => 100, 'product_upload_limit' => 50, 'duration' => 365]);
    $valid = ['package_id' => $packageId, 'payment_option' => 'manual_payment_1', 'trx_id' => 'T1'];
    $submit = function (array $body) use ($seller) {
        app('auth')->forgetGuards();

        return test()->withToken(storeApiToken($seller))->postJson('/temp/api/v2/seller/seller-package/offline-payment', $body);
    };

    foreach ([['package_id' => 9999], ['package_id' => null], ['trx_id' => null], ['payment_option' => null], ['photo' => '<b>x</b>']] as $forged) {
        $submit([...$valid, ...$forged])->assertUnprocessable();
    }
    expect(DB::table('seller_package_payments')->count())->toBe(0);

    $submit([...$valid, 'photo' => '7'])->assertJsonPath('result', true);
    $submit([...$valid, 'trx_id' => 'T2'])->assertJsonPath('result', false);
    $submit([...$valid, 'package_id' => $otherPackageId])->assertJsonPath('result', true);

    expect(DB::table('seller_package_payments')->where('seller_package_id', $packageId)->pluck('payment_details')->all())->toBe(['T1'])
        ->and(DB::table('seller_package_payments')->count())->toBe(2);
});

test('an account without a shop cannot submit an offline package payment', function () {
    createSellerPackageTables();
    $customer = createStoreUser('customer');
    $packageId = DB::table('seller_packages')->insertGetId(['name' => 'Gold', 'amount' => 500, 'product_upload_limit' => 500, 'duration' => 365]);

    $this->withToken(storeApiToken($customer))
        ->postJson('/temp/api/v2/seller/seller-package/offline-payment', ['package_id' => $packageId, 'payment_option' => 'manual_payment_1', 'trx_id' => 'T1'])
        ->assertNotFound();
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->postJson('/temp/api/v2/seller/seller-package/offline-payment', ['package_id' => $packageId])->assertUnauthorized();

    expect(DB::table('seller_package_payments')->count())->toBe(0);
});
