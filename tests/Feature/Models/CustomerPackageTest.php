<?php

use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;
use Illuminate\Support\Facades\DB;

test('customer package relations exclude other packages and translations work with and without eager loading', function () {
    $package = CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => 12.5, 'product_upload' => 10]);
    $other = CustomerPackage::forceCreate(['name' => 'Other']);
    foreach ([$package, $other] as $row) {
        CustomerPackageTranslation::create(['customer_package_id' => $row->id, 'lang' => 'ar', 'name' => $row->id === $package->id ? 'ذهبية' : 'أخرى']);
        DB::table('customer_package_payments')->insert(['customer_package_id' => $row->id, 'user_id' => 1, 'payment_method' => 'bank', 'payment_details' => 'reference', 'approval' => 0, 'offline_payment' => 1, 'reciept' => 'receipts/test.png']);
    }
    expect($package->customer_package_translations->pluck('name')->all())->toBe(['ذهبية'])
        ->and($package->customer_package_payments()->pluck('customer_package_id')->all())->toBe([$package->id]);
    foreach ([false, true] as $loaded) {
        $row = CustomerPackage::findOrFail($package->id);
        if ($loaded) {
            $row->load('customer_package_translations');
        }
        app()->setLocale('ar');
        expect($row->getTranslation('name'))->toBe('ذهبية')->and($row->getTranslation('name', 'en'))->toBe('Gold')
            ->and($row->getTranslation('name', 'ar'))->toBe('ذهبية');
    }
});
