<?php

use App\Http\Controllers\Store\Admin\VisitController;
use App\Models\StoreUser;
use App\Models\Visit;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function storeVisitRepresentative(): StoreUser
{
    $representative = createStoreUser('customer');
    $representative->forceFill(['is_rep' => true])->save();

    return $representative;
}

/** @param array<string, mixed> $attributes */
function storeAdminVisit(?StoreUser $representative = null, array $attributes = []): Visit
{
    return Visit::create([
        'rep_id' => ($representative ?? storeVisitRepresentative())->id,
        'customer_id' => createStoreUser('customer')->id,
        'visit_date' => '2026-10-09 12:00:00',
        'map_lat' => '0',
        'map_lng' => '0',
        'note' => 'Original visit note',
        ...$attributes,
    ])->fresh();
}

test('visit details render and keep the coordinates and ownership unchanged', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit();
    $original = $visit->getAttributes();

    $this->get(route('admin.rep.visits.show', $visit->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Visits/Show')->where('visit.id', $visit->id)->where('canUpdateNote', false));
    expect($visit->fresh()->getAttributes())->toBe($original);
});

test('visit details preserve order and related account data with safe text output', function () {
    seedStoreCurrency();
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    $visit = storeAdminVisit($representative);
    $visit->customer->forceFill(['AccSysID' => 'Customer account'])->save();
    $order = createStoreOrder($representative, [
        'by_rep' => 1,
        'for_customer' => $visit->customer_id,
        'delivery_status' => 'delivered',
        'delivery_date' => '2026-10-09',
        'additional_info' => '<script>alert(1)</script>',
    ]);
    $visit->forceFill(['order_id' => $order->id])->save();

    $this->get(route('admin.rep.visits.show', $visit->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Visits/Show')
            ->where('visit.order.code', $order->code)->where('visit.customer.accounting_id', 'Customer account')
            ->where('visit.order.customer_notes', '<script>alert(1)</script>')
            ->where('mapMarkers', [[
                'id' => $visit->id,
                'lat' => 0,
                'lng' => 0,
                'rank' => 1,
                'has_order' => true,
                'label' => $visit->customer->name,
            ]]));
    expect($visit->fresh()->only(['order_id', 'rep_id', 'customer_id']))
        ->toBe($visit->only(['order_id', 'rep_id', 'customer_id']));
});

test('visit map markers safely preserve zero and boundary coordinates and reject missing or invalid coordinates', function (mixed $latitude, mixed $longitude, bool $valid) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit(null, ['map_lat' => $latitude, 'map_lng' => $longitude]);
    $visit->customer->forceFill(['name' => '</script><img src=x onerror=alert(1)> "Customer"'])->save();
    $expected = $valid ? [[
        'id' => $visit->id,
        'lat' => json_decode(json_encode((float) $latitude)),
        'lng' => json_decode(json_encode((float) $longitude)),
        'rank' => 1,
        'has_order' => false,
        'label' => $visit->customer->name,
    ]] : [];

    foreach (['admin.rep.visits.show' => 'visit', 'admin.rep.visits.rep-show' => 'user'] as $route => $parameter) {
        $this->get(route($route, [$parameter => $parameter === 'visit' ? $visit->id : $visit->rep_id, 'date' => '2026-10-09']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('mapMarkers', $expected))
            ->assertDontSee('</script><img src=x onerror=alert(1)>', false);
    }
    expect($visit->fresh()->only(['map_lat', 'map_lng', 'rep_id', 'customer_id']))
        ->toBe($visit->only(['map_lat', 'map_lng', 'rep_id', 'customer_id']));
})->with([
    'zero' => ['0', '0', true],
    'boundaries' => ['90', '-180', true],
    'missing' => [null, null, false],
    'one missing' => ['51.5', null, false],
    'non numeric' => ['no latitude', '5', false],
    'latitude range' => ['91', '5', false],
    'longitude range' => ['51', '-181', false],
    'infinite' => ['1e999', '5', false],
]);

test('the Inertia shell shares the visit navigation permission for visit-only staff', function (bool $allowed) {
    actingAsStoreUser($allowed
        ? createStoreStaff('view_representative_visits')
        : createStoreStaff());

    $this->get(route('profile.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('store.permissions', $allowed ? ['view_representative_visits'] : [])
        ->where('store.isSuperAdmin', false)
        ->where('store.isRaizerAdmin', false));
})->with([true, false]);

test('the representative day screen scopes visits by owner and date and keeps their rank order', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    $first = storeAdminVisit($representative, ['rank' => 1]);
    $last = storeAdminVisit($representative, ['rank' => 4]);
    storeAdminVisit($representative, ['visit_date' => '2026-10-10 00:00:00']);
    storeAdminVisit(null, ['rank' => 9]);

    $this->get(route('admin.rep.visits.rep-show', ['user' => $representative->id, 'date' => '2026-10-09']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Visits/RepShow')
            ->where('date', '2026-10-09')->where('visitCount', 2)
            ->has('rep', fn (Assert $rep) => $rep->where('id', $representative->id)->where('name', $representative->name))
            ->where('mapMarkers', [
                ['id' => $last->id, 'lat' => 0, 'lng' => 0, 'rank' => 5, 'has_order' => false, 'label' => $last->customer->name],
                ['id' => $first->id, 'lat' => 0, 'lng' => 0, 'rank' => 2, 'has_order' => false, 'label' => $first->customer->name],
            ]));
});

test('the representative day screen defaults to the first visit day or today', function (bool $hasVisit) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    if ($hasVisit) {
        storeAdminVisit($representative);
    }

    $this->get(route('admin.rep.visits.rep-show', $representative->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', $hasVisit ? '2026-10-09' : now()->toDateString())->where('visitCount', $hasVisit ? 1 : 0));
})->with([true, false]);

test('the representative day screen rejects invalid date inputs', function (mixed $date) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));

    $this->get(route('admin.rep.visits.rep-show', ['user' => storeVisitRepresentative()->id, 'date' => $date]))
        ->assertSessionHasErrors('date');
})->with(['yesterday', '2026-02-31', 'array' => [['2026-10-09']]]);

test('the representative day screen refuses users without the representative flag', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $customer = createStoreUser('customer');

    $this->get(route('admin.rep.visits.rep-show', $customer->id))->assertNotFound();
});

test('missing visits and representatives return 404', function (string $route) {
    actingAsStoreUser(createStoreStaff('view_representative_visits', 'delete_representative_visit'));

    $response = $route === 'admin.rep.visits.destroy'
        ? $this->delete(route($route, 999999))
        : $this->get(route($route, 999999));
    $response->assertNotFound();
})->with(['admin.rep.visits.show', 'admin.rep.visits.rep-show', 'admin.rep.visits.destroy']);

test('representative web visit notes reject malformed writes before saving', function () {
    $owner = createStoreUser('customer');
    $owner->forceFill(['is_rep' => 1])->save();
    $visit = Visit::forceCreate(['rep_id' => $owner->id, 'customer_id' => createStoreUser('customer')->id, 'note' => 'Original note']);
    actingAsStoreUser($owner);
    $this->postJson(route('visits.note'), ['visit_id' => $visit->id, 'note' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson(route('visits.note'), ['visit_id' => 'invalid', 'note' => 'Ignored'])->assertUnprocessable()->assertJsonValidationErrors('visit_id');
    expect($visit->fresh()->note)->toBe('Original note');
});

test('visit detail returns its full narrow payload with its order and owner', function () {
    seedStoreCurrency();
    $actor = createStoreStaff('view_representative_visits');
    actingAsStoreUser($actor);
    $customer = createStoreUser('customer');
    $customer->forceFill(['name' => 'Noor', 'company_name' => 'Noor BV', 'email' => 'noor@example.com', 'phone' => '0612345678', 'member_serial' => 42, 'AccSysID' => 'AC-21'])->save();
    $order = createStoreOrder($actor, ['code' => 'VISIT-31', 'by_rep' => 1, 'for_customer' => $customer->id, 'date' => 1791547200, 'grand_total' => 12.50, 'payment_type' => 'cash_on_delivery', 'additional_info' => 'Leave at reception', 'delivery_date' => '2026-10-10', 'delivery_status' => 'delivered']);
    $visit = storeAdminVisit(null, ['rep_id' => $actor->id, 'customer_id' => $customer->id, 'order_id' => $order->id]);
    storeAdminVisit();

    $this->get(route('admin.rep.visits.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Representatives/Visits/Show')->where('canUpdateNote', true)
        ->has('visit', fn (Assert $shown) => $shown->where('id', $visit->id)->where('note', 'Original visit note')->where('visit_date', '2026-10-09 12:00:00')
            ->where('representative', ['id' => $actor->id, 'name' => $actor->name])
            ->where('customer', ['id' => $customer->id, 'name' => 'Noor', 'company_name' => 'Noor BV', 'email' => 'noor@example.com', 'phone' => '0612345678', 'member_serial' => '42', 'accounting_id' => 'AC-21'])
            ->where('order', ['id' => $order->id, 'code' => 'VISIT-31', 'delivery_status' => 'delivered', 'date' => 1791547200, 'amount' => single_price(12.50), 'payment_type' => translate('Cash on delivery'), 'customer_notes' => 'Leave at reception', 'customer_otajer_id' => 'AC-21', 'delivery_date' => '2026-10-10']))
        ->where('mapMarkers', [['id' => $visit->id, 'lat' => 0, 'lng' => 0, 'rank' => 1, 'has_order' => true, 'label' => 'Noor']]));
});

test('visit detail safely renders dangling related records and null notes', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit(null, ['rep_id' => 999998, 'customer_id' => 999999, 'order_id' => 999999, 'note' => null, 'visit_date' => null]);
    $this->get(route('admin.rep.visits.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('visit', fn (Assert $shown) => $shown->where('id', $visit->id)->where('note', null)->where('visit_date', null)->where('representative', null)->where('customer', null)->where('order', null))
        ->where('canUpdateNote', false)->where('mapMarkers.0.label', ''));
});

test('visit day query cannot override the bound user and absent days render empty', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $rep = storeVisitRepresentative();
    $other = storeAdminVisit();
    $this->get(route('admin.rep.visits.rep-show', ['user' => $rep->id, 'date' => '2026-10-09']).'&user='.$other->rep_id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('rep.id', $rep->id)->where('date', '2026-10-09')->where('visitCount', 0)->where('mapMarkers', []));
});

test('admin visit notes save only the actors own visit and ignore a forged body id', function () {
    $actor = createStoreStaff('view_representative_visits');
    actingAsStoreUser($actor);
    $own = storeAdminVisit(null, ['rep_id' => $actor->id]);
    $foreign = storeAdminVisit();
    $before = $foreign->getAttributes();
    $url = route('admin.rep.visits.show', $own);

    $this->from($url)->put(route('admin.rep.visits.note', $own), ['visit_id' => $foreign->id, 'note' => 'My new note'])->assertRedirect($url);
    expect($own->fresh()->note)->toBe('My new note')->and($foreign->fresh()->getAttributes())->toBe($before);
    $this->from($url)->put(route('admin.rep.visits.note', $foreign), ['note' => 'Foreign note'])->assertRedirect($url);
    expect($foreign->fresh()->getAttributes())->toBe($before);
    $this->from($url)->put(route('admin.rep.visits.note', $own), ['note' => null])->assertRedirect($url);
    expect($own->fresh()->note)->toBeNull();
});

test('Super Admin cannot rewrite another representative visit note', function () {
    actingAsStoreUser(createStoreAdmin());
    $visit = storeAdminVisit();
    $before = $visit->getAttributes();
    $this->put(route('admin.rep.visits.note', $visit), ['note' => 'Not mine'])->assertRedirect();
    expect($visit->fresh()->getAttributes())->toBe($before);
});

test('the retained customer visit writer redirects back for own and foreign visits', function () {
    $owner = storeVisitRepresentative();
    $visit = storeAdminVisit($owner);
    $other = storeAdminVisit();
    $before = $other->getAttributes();
    actingAsStoreUser($owner);
    $url = url('/temp/view-customer');
    $this->from($url)->post(route('visits.note'), ['visit_id' => $other->id, 'note' => 'hijacked'])->assertRedirect($url);
    expect($other->fresh()->getAttributes())->toBe($before);
    $this->from($url)->post(route('visits.note'), ['visit_id' => $visit->id, 'note' => 'web note'])->assertRedirect($url);
    expect($visit->fresh()->note)->toBe('web note');
    $this->from($url)->post(route('visits.note'), ['visit_id' => $visit->id, 'note' => ''])->assertRedirect($url);
    expect($visit->fresh()->note)->toBeNull();
});

test('admin visit notes reject malformed notes without any write', function () {
    $actor = createStoreStaff('view_representative_visits');
    actingAsStoreUser($actor);
    $visit = storeAdminVisit(null, ['rep_id' => $actor->id]);
    $before = $visit->getAttributes();
    $this->putJson(route('admin.rep.visits.note', $visit), ['note' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('note');
    expect($visit->fresh()->getAttributes())->toBe($before);
});

test('missing visit ids return 404 on both note routes', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $this->put(route('admin.rep.visits.note', 999999), ['note' => 'Unknown'])->assertNotFound();
    actingAsStoreUser(storeVisitRepresentative());
    $this->post(route('visits.note'), ['visit_id' => 999999, 'note' => 'Unknown'])->assertNotFound();
});

test('visit detail and day routes refuse unauthorized callers', function (string $caller) {
    $visit = storeAdminVisit();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('admin.rep.visits.show', $visit)), $caller);
    assertStoreCallerRefused($this->get(route('admin.rep.visits.rep-show', $visit->rep_id)), $caller);
})->with('refused store admin callers');

test('the admin note alias refuses unauthorized callers without changing the database', function (string $caller) {
    $visit = storeAdminVisit();
    $before = $visit->getAttributes();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put(route('admin.rep.visits.note', $visit), ['note' => 'Refused']), $caller);
    expect($visit->fresh()->getAttributes())->toBe($before);
})->with('refused store admin callers');

test('visit routes preserve their permissions and the retained customer boundary', function () {
    foreach (['admin.rep.visits.show', 'admin.rep.visits.rep-show', 'admin.rep.visits.note'] as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route->getActionName())->toStartWith(VisitController::class.'@');
        expect($route->gatherMiddleware())->toContain('permission:view_representative_visits');
    }
    expect(Route::getRoutes()->getByName('visits.note')->gatherMiddleware())->toContain('customer', 'verified', 'unbanned');
    expect(file_exists(app_path('Http/Controllers/VisitController.php')))->toBeFalse();
    foreach (['show', 'rep-show', 'map'] as $view) {
        expect(view()->exists('shop.backend.representatives.visits.'.$view))->toBeFalse();
    }
    $source = file_get_contents(resource_path('views/shop/frontend/view_customer.blade.php'));
    expect($source)->toContain('id="visit-note-form"', 'name="visit_id"', 'name="note"', 'method="POST"', "route('visits.note')")->not->toContain('data == 1', 'update_notes');
});
