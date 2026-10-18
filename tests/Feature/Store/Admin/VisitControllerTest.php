<?php

use App\Models\Visit;
use Inertia\Testing\AssertableInertia as Assert;

function adminRepresentativeVisit(array $attributes = []): Visit
{
    return Visit::create(['rep_id' => createStoreUser('customer')->id, 'customer_id' => createStoreUser('customer')->id, 'visit_date' => '2026-10-08 10:00:00', 'note' => 'Visit note', ...$attributes]);
}

test('the visit index renders', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $this->get('/temp/admin/representatives/visits/index')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Visits/Index'));
});

test('visits return the complete narrow payload with nullable data', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = adminRepresentativeVisit(['note' => '<script>bad()</script>', 'visit_date' => null]);
    $visit->load(['rep', 'customer']);
    $this->get('/temp/admin/representatives/visits/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['per_page' => 20, 'search' => '', 'date_from' => '', 'date_to' => ''])
        ->has('visits.data.0', fn (Assert $item) => $item->where('id', $visit->id)->where('note', '<script>bad()</script>')->where('visit_date', null)->where('has_order', false)
            ->has('representative', fn (Assert $user) => $user->where('id', $visit->rep_id)->where('name', $visit->rep->name))
            ->has('customer', fn (Assert $user) => $user->where('id', $visit->customer_id)->where('name', $visit->customer->name))));
});

test('visits paginate and filter dates and representative names with unrelated rows excluded', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $rep = createStoreUser('customer');
    $rep->forceFill(['name' => 'Match Representative'])->save();
    foreach (range(1, 22) as $i) {
        adminRepresentativeVisit(['rep_id' => $rep->id]);
    }
    adminRepresentativeVisit(['visit_date' => '2026-10-01 10:00:00']);
    adminRepresentativeVisit();
    $this->get('/temp/admin/representatives/visits/index')->assertOk()->assertInertia(fn (Assert $page) => $page->has('visits.data', 20)->where('visits.meta.total', 24));
    $this->get('/temp/admin/representatives/visits/index?search=Match&date_from=2026-10-08&date_to=2026-10-08&per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('visits.data', 10)->where('visits.meta.total', 22));
    $this->getJson('/temp/admin/representatives/visits/index?per_page=999')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/temp/admin/representatives/visits/index?date_from=wrong')->assertUnprocessable()->assertJsonValidationErrors('date_from');
    $this->getJson('/temp/admin/representatives/visits/index?date_from=2026-10-08&date_to=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('date_to');
    $this->getJson('/temp/admin/representatives/visits/index?search='.str_repeat('a', 256))->assertUnprocessable()->assertJsonValidationErrors('search');
});

test('single visit deletion preserves unrelated visits and filter query', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_visit'));
    $visit = adminRepresentativeVisit();
    $noise = adminRepresentativeVisit();
    $this->delete('/temp/admin/representatives/visits/delete/'.$visit->id.'?page=2&search=Match')->assertRedirect(route('admin.rep.visits.index', ['page' => 2, 'search' => 'Match']));
    $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
    $this->assertDatabaseHas('visits', ['id' => $noise->id]);
});

test('bulk deletion removes only selected visits and preserves filter query', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_visit'));
    $visit = adminRepresentativeVisit();
    $second = adminRepresentativeVisit();
    $noise = adminRepresentativeVisit();
    $this->post('/temp/admin/representatives/visits/bulk-visit-delete?per_page=10', ['id' => [$visit->id, $second->id]])->assertRedirect(route('admin.rep.visits.index', ['per_page' => 10]));
    $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
    $this->assertDatabaseMissing('visits', ['id' => $second->id]);
    $this->assertDatabaseHas('visits', ['id' => $noise->id]);
});

test('bulk deletion validates the whole selection before deleting anything', function (string $kind) {
    actingAsStoreUser(createStoreStaff('delete_representative_visit'));
    $visit = adminRepresentativeVisit();
    $ids = match ($kind) {
        'missing' => [$visit->id, 999999],
        'duplicate' => [$visit->id, $visit->id],
        'empty' => [],
        'large' => range(1, 101),
        default => ['invalid'],
    };
    $this->postJson('/temp/admin/representatives/visits/bulk-visit-delete', ['id' => $ids])->assertUnprocessable();
    $this->assertDatabaseHas('visits', ['id' => $visit->id]);
})->with(['missing', 'duplicate', 'empty', 'large', 'invalid']);

test('destroy of a missing visit returns 404', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_visit'));
    $this->delete('/temp/admin/representatives/visits/delete/999999')->assertNotFound();
});

test('visit index refuses unauthorized callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/representatives/visits/index'), $caller);
})->with('refused store admin callers');

test('visit destroy refuses unauthorized callers without writes', function (string $caller) {
    $visit = adminRepresentativeVisit();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/representatives/visits/delete/'.$visit->id), $caller);
    $this->assertDatabaseHas('visits', ['id' => $visit->id]);
})->with('refused store admin callers');

test('visit bulk destroy refuses unauthorized callers without writes', function (string $caller) {
    $visit = adminRepresentativeVisit();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/representatives/visits/bulk-visit-delete', ['id' => [$visit->id]]), $caller);
    $this->assertDatabaseHas('visits', ['id' => $visit->id]);
})->with('refused store admin callers');

test('read visit permission cannot delete visits', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = adminRepresentativeVisit();
    $this->delete('/temp/admin/representatives/visits/delete/'.$visit->id)->assertForbidden();
    $this->post('/temp/admin/representatives/visits/bulk-visit-delete', ['id' => [$visit->id]])->assertForbidden();
    $this->assertDatabaseHas('visits', ['id' => $visit->id]);
});

test('visit end date alone accepts both omitted and empty start dates', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    adminRepresentativeVisit(['visit_date' => '2026-10-01 12:00:00']);
    adminRepresentativeVisit(['visit_date' => '2026-10-08 12:00:00']);
    $this->get('/temp/admin/representatives/visits/index?date_to=2026-10-01')->assertOk()->assertInertia(fn (Assert $page) => $page->has('visits.data', 1));
    $this->get('/temp/admin/representatives/visits/index?date_from=&date_to=2026-10-01')->assertOk()->assertInertia(fn (Assert $page) => $page->has('visits.data', 1));
});

test('visit order existence ignores a dangling order id and missing user summaries remain null', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $order = createStoreOrder(createStoreUser('customer'));
    $withOrder = adminRepresentativeVisit(['order_id' => $order->id, 'rep_id' => 999998, 'customer_id' => 999999]);
    $dangling = adminRepresentativeVisit(['order_id' => 999999]);
    $this->get('/temp/admin/representatives/visits/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('visits.data.0.id', $dangling->id)->where('visits.data.0.has_order', false)
        ->where('visits.data.1.id', $withOrder->id)->where('visits.data.1.has_order', true)
        ->where('visits.data.1.representative', null)->where('visits.data.1.customer', null));
});
