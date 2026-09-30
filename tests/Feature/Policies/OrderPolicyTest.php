<?php

use App\Policies\OrderPolicy;
use Spatie\Permission\Models\Permission;

test('an order is visible to the account that placed it and nobody else', function () {
    $customer = createStoreUser('customer');
    $otherCustomer = createStoreUser('customer');
    $order = createStoreOrder($customer);

    $policy = new OrderPolicy;

    expect($policy->view($customer, $order))->toBeTrue()
        ->and($policy->view($otherCustomer, $order))->toBeFalse();
});

test('an order a representative placed is visible to the representative and the customer it was for', function () {
    $representative = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $otherCustomer = createStoreUser('customer');
    $order = createStoreOrder($representative, ['by_rep' => 1, 'for_customer' => $customer->id]);

    $policy = new OrderPolicy;

    expect($policy->view($representative, $order))->toBeTrue()
        ->and($policy->view($customer, $order))->toBeTrue()
        ->and($policy->view($otherCustomer, $order))->toBeFalse()
        ->and($policy->downloadInvoice($customer, $order))->toBeTrue()
        ->and($policy->downloadInvoice($otherCustomer, $order))->toBeFalse();
});

test('the customer who placed an order may download its invoice, another customer may not', function () {
    $customer = createStoreUser('customer');
    $otherCustomer = createStoreUser('customer');
    $order = createStoreOrder($customer);

    $policy = new OrderPolicy;

    expect($policy->downloadInvoice($customer, $order))->toBeTrue()
        ->and($policy->downloadInvoice($otherCustomer, $order))->toBeFalse();
});

test('the seller an order was placed with may download its invoice, another seller may not', function () {
    $seller = createStoreUser('seller');
    $otherSeller = createStoreUser('seller');
    $order = createStoreOrder(createStoreUser('customer'), ['seller_id' => $seller->id]);

    $policy = new OrderPolicy;

    expect($policy->downloadInvoice($seller, $order))->toBeTrue()
        ->and($policy->downloadInvoice($otherSeller, $order))->toBeFalse();
});

test('staff who can see orders in the back office may download any invoice', function (string $permission) {
    Permission::findOrCreate($permission, 'store');
    $staff = createStoreUser('staff');
    $staff->givePermissionTo($permission);

    expect((new OrderPolicy)->downloadInvoice($staff, createStoreOrder(createStoreUser('customer'))))->toBeTrue();
})->with([
    'view_all_orders',
    'view_inhouse_orders',
    'view_seller_orders',
    'view_pickup_point_orders',
    'view_order_details',
]);

test('staff with only permissions unrelated to orders may not', function () {
    Permission::findOrCreate('view_order_details', 'store');
    Permission::findOrCreate('view_all_customers', 'store');
    $staff = createStoreUser('staff');
    $staff->givePermissionTo('view_all_customers');

    expect((new OrderPolicy)->downloadInvoice($staff, createStoreOrder(createStoreUser('customer'))))->toBeFalse();
});
