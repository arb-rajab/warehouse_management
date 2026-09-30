<?php

use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

/**
 * Downloading an order's invoice: the storefront's `invoice.download`, which
 * the back office's order pages link too, and the seller area's
 * `seller.invoice.download`. Both found the order by its id alone, so any
 * signed-in store account could download any customer's invoice (their
 * name, email, phone and what they ordered) by counting through order ids.
 * OrderPolicy::downloadInvoice now decides.
 *
 * mPDF's download() sends the file itself, with header() and echo, rather
 * than returning it in the response, so these read it from an output buffer.
 *
 * @return array{TestResponse, string}
 */
function downloadInvoice(string $url): array
{
    ob_start();

    try {
        $response = test()->get($url);
    } finally {
        $output = (string) ob_get_clean();
    }

    return [$response, $output];
}

beforeEach(function () {
    seedStoreCurrency();
});

test('a customer can download the invoice of their own order', function () {
    $customer = actingAsStoreUser(createStoreUser('customer'));

    [$response, $pdf] = downloadInvoice(route('invoice.download', createStoreOrder($customer)->id));

    $response->assertOk();
    // mPDF keeps its temporary files in storage/, not in a project-root temp/
    // this repository doesn't ignore.
    expect($pdf)->toStartWith('%PDF-')
        ->and(storage_path('framework/cache/mpdf'))->toBeDirectory();
});

test("a customer cannot download another customer's invoice", function () {
    $order = createStoreOrder(createStoreUser('customer'));
    actingAsStoreUser(createStoreUser('customer'));

    [$response, $output] = downloadInvoice(route('invoice.download', $order->id));

    $response->assertForbidden();
    expect($output)->toBe('');
});

test('a guest is sent to the store login instead of an invoice', function () {
    $order = createStoreOrder(createStoreUser('customer'));

    [$response, $output] = downloadInvoice(route('invoice.download', $order->id));

    $response->assertRedirect(route('shop.login'));
    expect($output)->toBe('');
});

test("back-office staff who can see orders can download a customer's invoice", function () {
    Permission::findOrCreate('view_order_details', 'store');
    $staff = createStoreUser('staff');
    $staff->givePermissionTo('view_order_details');
    actingAsStoreUser($staff);

    [$response, $pdf] = downloadInvoice(route('invoice.download', createStoreOrder(createStoreUser('customer'))->id));

    $response->assertOk();
    expect($pdf)->toStartWith('%PDF-');
});

test("back-office staff without an order permission cannot download a customer's invoice", function () {
    Permission::findOrCreate('view_order_details', 'store');
    actingAsStoreUser(createStoreUser('staff'));

    [$response, $output] = downloadInvoice(route('invoice.download', createStoreOrder(createStoreUser('customer'))->id));

    $response->assertForbidden();
    expect($output)->toBe('');
});

test("a store Super Admin can download any customer's invoice", function () {
    actingAsStoreUser(createStoreAdmin());

    [$response, $pdf] = downloadInvoice(route('invoice.download', createStoreOrder(createStoreUser('customer'))->id));

    $response->assertOk();
    expect($pdf)->toStartWith('%PDF-');
});

test('a seller can download the invoice of an order placed with them from the seller area', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));
    $order = createStoreOrder(createStoreUser('customer'), ['seller_id' => $seller->id]);

    // In Arabic, which the invoice sets in a font mPDF bundles. The store's
    // other invoice fonts are read from public/assets/fonts, which this
    // repository doesn't carry yet.
    $this->withSession(['locale' => 'sa']);

    [$response, $pdf] = downloadInvoice(route('seller.invoice.download', $order->id));

    $response->assertOk();
    expect($pdf)->toStartWith('%PDF-');
});

test("a seller cannot download the invoice of another seller's order", function () {
    $order = createStoreOrder(createStoreUser('customer'), ['seller_id' => createStoreUser('seller')->id]);
    actingAsStoreUser(createStoreUser('seller'));

    [$response, $output] = downloadInvoice(route('seller.invoice.download', $order->id));

    $response->assertForbidden();
    expect($output)->toBe('');
});
