<?php

use App\Models\Address;
use App\Policies\AddressPolicy;

test('a store account may manage its own address and not another account\'s', function () {
    $customer = createStoreUser('customer');
    $address = Address::findOrFail(addStoreAddress($customer->id));

    $policy = new AddressPolicy;

    expect($policy->manage($customer, $address))->toBeTrue()
        ->and($policy->manage(createStoreUser('customer'), $address))->toBeFalse()
        ->and($policy->manage(createStoreUser('seller'), $address))->toBeFalse();
});
