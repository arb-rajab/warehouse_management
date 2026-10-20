<?php

use App\Models\StoreUser;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * A representative's payment receipt (its page, its PDF and its QR code) was
 * reachable by anyone who knew the receipt's number, which counts up from 1, and
 * it names the customer, the company and the amount. The links the store hands
 * out are signed now, so only a link it issued opens one; the QR-image endpoint
 * that would sign anything for anyone needs the representative who owns the
 * payment; and a visit note could be rewritten by any representative.
 */
function repUser(): StoreUser
{
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => 1])->save();

    return $rep;
}

function receiptFor(StoreUser $rep, ?StoreUser $customer = null): string
{
    $id = (string) random_int(1000, 999999);
    DB::table('representative_payments')->insert([
        'id' => $id, 'amount' => 25, 'receipt_code' => 'R-'.$id, 'rep_id' => $rep->id, 'customer_id' => $customer?->id,
        'customer_name' => $customer?->name, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

test('a receipt opens only through a link the store signed', function () {
    seedStoreCurrency();
    $id = receiptFor(repUser(), createStoreUser('customer'));

    $this->get(route('payments.show', ['id' => $id]))->assertForbidden();
    $this->get(route('payments.export', ['id' => $id]))->assertForbidden();

    $this->get(URL::signedRoute('payments.show', ['id' => $id]))->assertOk();

    // The PDF is written to the output as it is built, which would end up in the test report.
    ob_start();
    $exportStatus = $this->get(URL::signedRoute('payments.export', ['id' => $id]))->getStatusCode();
    ob_end_clean();

    expect($exportStatus)->toBe(200);
});

test('a signature for one receipt does not open another', function () {
    $rep = repUser();
    $mine = receiptFor($rep);
    $other = receiptFor($rep);

    $link = URL::signedRoute('payments.show', ['id' => $mine]);

    $this->get(str_replace("show/$mine", "show/$other", $link))->assertForbidden();
});

test('the QR image of a receipt is made for the representative who owns it, and for no one else', function () {
    $owner = repUser();
    $stranger = repUser();
    $id = receiptFor($owner);

    expect($this->get(route('payments.generate_qr', ['id' => $id]))->getStatusCode())->toBeIn([302, 401, 403]);

    actingAsStoreUser($stranger);
    $this->get(route('payments.generate_qr', ['id' => $id]))->assertNotFound();

    actingAsStoreUser($owner);
    $this->get(route('payments.generate_qr', ['id' => $id]))->assertOk()->assertSee('<svg', false);
});

test('only the owning representative can send a receipt on', function () {
    $owner = repUser();
    $stranger = repUser();
    $customer = createStoreUser('customer');
    $customer->forceFill(['phone' => '0612345678'])->save();
    $id = receiptFor($owner, $customer);

    actingAsStoreUser($stranger);
    $this->get(route('payments.share-by-whatsapp', ['id' => $id]))->assertNotFound();

    actingAsStoreUser($owner);
    $this->get(route('payments.share-by-whatsapp', ['id' => $id]))->assertRedirectContains('signature=');
});

test('the app\'s receipt list carries signed links, and the QR endpoint needs its signature', function () {
    $rep = repUser();
    $id = receiptFor($rep);

    $item = $this->withToken(storeApiToken($rep))->getJson('/temp/api/v2/representatives/payments/index')->assertOk()->json('data.0');

    expect(URL::hasValidSignature(Request::create($item['pdf_url'])))->toBeTrue()
        ->and(URL::hasValidSignature(Request::create($item['qr'])))->toBeTrue();

    $this->get($item['qr'])->assertOk();
    $this->get("/temp/api/v2/representatives/payments/generate-qr?id=$id")->assertForbidden();
});

test('the mobile API changes the note of the representative own visit only', function () {
    $owner = repUser();
    $stranger = repUser();
    $customer = createStoreUser('customer');
    $visit = Visit::create(['rep_id' => $owner->id, 'customer_id' => $customer->id, 'note' => 'original']);

    $this->withToken(storeApiToken($stranger))->postJson('/temp/api/v2/representatives/visit-note', ['visit_id' => $visit->id, 'note' => 'hijacked'])->assertNotFound();
    expect($visit->fresh()->note)->toBe('original');

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($owner))->postJson('/temp/api/v2/representatives/visit-note', ['visit_id' => $visit->id, 'note' => 'mine'])->assertOk();
    expect($visit->fresh()->note)->toBe('mine');

});
