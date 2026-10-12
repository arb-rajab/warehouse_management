<?php

use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;

test('customer package translation belongs only to its own package', function () {
    $package = CustomerPackage::forceCreate(['name' => 'Gold']);
    $other = CustomerPackage::forceCreate(['name' => 'Other']);
    $translation = CustomerPackageTranslation::create(['customer_package_id' => $package->id, 'lang' => 'en', 'name' => 'Gold']);
    CustomerPackageTranslation::create(['customer_package_id' => $other->id, 'lang' => 'en', 'name' => 'Other']);
    expect($translation->customer_package()->first()->id)->toBe($package->id)->not->toBe($other->id);
});
