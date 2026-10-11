<?php

use App\Models\StoreUser;
use App\Models\Visit;

/**
 * Safe prerequisites for the visit migration. Map screens remain Blade until
 * the Leaflet dependency is approved; permissions also protect those screens.
 */
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

test('the visit list renders for a staff member with its read permission', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit();

    $this->get(route('admin.rep.visits.index'))->assertOk()
        ->assertViewIs('shop.backend.representatives.visits.index')
        ->assertSee('Original visit note');
});

test('the visit list eager loads its row relations and paginates', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    $ids = collect(range(1, 17))->map(fn () => storeAdminVisit($representative)->id);

    $this->get(route('admin.rep.visits.index'))->assertOk()->assertViewHas('visits', function ($visits) use ($ids) {
        return $visits->total() === 17 && $visits->count() === 15
            && $visits->pluck('id')->intersect($ids)->count() === 15
            && $visits->every(fn (Visit $visit) => $visit->relationLoaded('customer') && $visit->relationLoaded('rep') && $visit->relationLoaded('order'));
    });
});

test('the visit list offers deletion only with its delete permission', function (bool $canDelete) {
    actingAsStoreUser($canDelete
        ? createStoreStaff('view_representative_visits', 'delete_representative_visits')
        : createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit();

    $response = $this->get(route('admin.rep.visits.index'))->assertOk();
    if ($canDelete) {
        $response->assertSee('onclick="bulk_delete()"', false)
            ->assertSee('data-href="'.route('admin.rep.visits.destroy', $visit->id).'"', false);
    } else {
        $response->assertDontSee('onclick="bulk_delete()"', false)
            ->assertDontSee('data-href="'.route('admin.rep.visits.destroy', $visit->id).'"', false);
    }
})->with([true, false]);

test('the visit list filters by the representative or customer and the entire inclusive day', function (string $range) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    $representative->forceFill(['name' => 'Selected Representative'])->save();
    $first = storeAdminVisit($representative, ['visit_date' => '2026-10-09 00:00:00']);
    $last = storeAdminVisit($representative, ['visit_date' => '2026-10-09 23:59:59']);
    storeAdminVisit($representative, ['visit_date' => '2026-10-10 00:00:00']);
    storeAdminVisit(null, ['visit_date' => '2026-10-09 12:00:00']);

    $this->get(route('admin.rep.visits.index', ['date' => $range, 'search' => 'Selected Representative']))
        ->assertOk()->assertViewHas('visits', fn ($visits) => $visits->pluck('id')->sort()->values()->all() === [$first->id, $last->id]);

    $customer = $first->customer;
    $customer->forceFill(['name' => 'Selected Customer'])->save();
    $this->get(route('admin.rep.visits.index', ['search' => 'Selected Customer']))
        ->assertOk()->assertViewHas('visits', fn ($visits) => $visits->pluck('id')->all() === [$first->id]);
})->with(['09-10-2026 to 09-10-2026', '2026-10-09 to 2026-10-09']);

test('the visit list rejects malformed impossible and reversed date ranges', function (mixed $range) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));

    $this->get(route('admin.rep.visits.index', ['date' => $range]))->assertSessionHasErrors('date');
})->with(['yesterday', '2026-10-09', '2026-02-31 to 2026-03-01', '2026-10-10 to 2026-10-09', 'array' => [['2026-10-09']]]);

test('visit details render and keep the coordinates and ownership unchanged', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit();
    $original = $visit->getAttributes();

    $this->get(route('admin.rep.visits.show', $visit->id))->assertOk()
        ->assertViewIs('shop.backend.representatives.visits.show')
        ->assertViewHas('visit', fn (Visit $shown) => $shown->getAttributes() === $original);
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
        ->assertSee($order->code)->assertSee('Customer account')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertViewHas('mapMarkers', [[
            'id' => $visit->id,
            'lat' => 0.0,
            'lng' => 0.0,
            'rank' => 1,
            'has_order' => true,
            'label' => $visit->customer->name,
        ]]);
    expect($visit->fresh()->only(['order_id', 'rep_id', 'customer_id']))
        ->toBe($visit->only(['order_id', 'rep_id', 'customer_id']));
});

test('visit map markers safely preserve zero and boundary coordinates and reject missing or invalid coordinates', function (mixed $latitude, mixed $longitude, bool $valid) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $visit = storeAdminVisit(null, ['map_lat' => $latitude, 'map_lng' => $longitude]);
    $visit->customer->forceFill(['name' => '</script><img src=x onerror=alert(1)> "Customer"'])->save();
    $expected = $valid ? [[
        'id' => $visit->id,
        'lat' => (float) $latitude,
        'lng' => (float) $longitude,
        'rank' => 1,
        'has_order' => false,
        'label' => $visit->customer->name,
    ]] : [];

    foreach (['admin.rep.visits.show' => $visit->id, 'admin.rep.visits.rep-show' => $visit->rep_id] as $route => $id) {
        $this->get(route($route, ['id' => $id, 'date' => '2026-10-09']))->assertOk()
            ->assertViewHas('mapMarkers', $expected)
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

test('the Blade navigation exposes visits to visit-only staff and hides it without permission', function (bool $allowed) {
    actingAsStoreUser($allowed ? createStoreStaff('view_representative_visits') : createStoreStaff());
    $response = $this->get(route('shop.admin.dashboard'))->assertOk();

    if ($allowed) {
        $response->assertSee('href="'.route('admin.rep.visits.index').'"', false);
    } else {
        $response->assertDontSee('href="'.route('admin.rep.visits.index').'"', false);
    }
})->with([true, false]);

test('the representative day screen scopes visits by owner and date and keeps their rank order', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    $first = storeAdminVisit($representative, ['rank' => 1]);
    $last = storeAdminVisit($representative, ['rank' => 4]);
    storeAdminVisit($representative, ['visit_date' => '2026-10-10 00:00:00']);
    storeAdminVisit(null, ['rank' => 9]);

    $this->get(route('admin.rep.visits.rep-show', ['id' => $representative->id, 'date' => '2026-10-09']))->assertOk()
        ->assertViewIs('shop.backend.representatives.visits.rep-show')
        ->assertViewHas('date', '2026-10-09')
        ->assertViewHas('rep', fn (StoreUser $rep) => $rep->id === $representative->id)
        ->assertViewHas('visits', fn ($visits) => $visits->pluck('id')->all() === [$last->id, $first->id]);
});

test('the representative day screen defaults to the first visit day or today', function (bool $hasVisit) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $representative = storeVisitRepresentative();
    if ($hasVisit) {
        storeAdminVisit($representative);
    }

    $this->get(route('admin.rep.visits.rep-show', $representative->id))->assertOk()
        ->assertViewHas('date', $hasVisit ? '2026-10-09' : now()->toDateString());
})->with([true, false]);

test('the representative day screen rejects invalid date inputs', function (mixed $date) {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));

    $this->get(route('admin.rep.visits.rep-show', ['id' => storeVisitRepresentative()->id, 'date' => $date]))
        ->assertSessionHasErrors('date');
})->with(['yesterday', '2026-02-31', 'array' => [['2026-10-09']]]);

test('the representative day screen refuses users without the representative flag', function () {
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $customer = createStoreUser('customer');

    $this->get(route('admin.rep.visits.rep-show', $customer->id))->assertNotFound();
});

test('missing visits and representatives return 404', function (string $route) {
    actingAsStoreUser(createStoreStaff('view_representative_visits', 'delete_representative_visits'));

    $response = $route === 'admin.rep.visits.destroy'
        ? $this->post(route($route, 999999))
        : $this->get(route($route, 999999));
    $response->assertNotFound();
})->with(['admin.rep.visits.show', 'admin.rep.visits.rep-show', 'admin.rep.visits.destroy']);

test('authorized visit deletion preserves the list query and only removes the selected visit', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_visits'));
    $visit = storeAdminVisit();
    $other = storeAdminVisit();

    $this->post(route('admin.rep.visits.destroy', ['id' => $visit->id, 'search' => 'rep', 'page' => 2]))
        ->assertRedirect(route('admin.rep.visits.index', ['search' => 'rep', 'page' => 2]));
    expect(Visit::find($visit->id))->toBeNull()
        ->and(Visit::find($other->id))->not->toBeNull();
});

test('authorized bulk visit deletion keeps unrelated visits', function () {
    actingAsStoreUser(createStoreStaff('delete_representative_visits'));
    $first = storeAdminVisit();
    $second = storeAdminVisit();
    $other = storeAdminVisit();

    $this->post(route('bulk-visit-delete'), ['id' => [$first->id, $second->id]])->assertOk()->assertContent('1');
    expect(Visit::query()->pluck('id')->all())->toBe([$other->id]);
});

test('bulk deletion validates the complete selection before deleting anything', function (string $invalid) {
    actingAsStoreUser(createStoreStaff('delete_representative_visits'));
    $visit = storeAdminVisit();
    $ids = match ($invalid) {
        'missing' => null,
        'empty' => [],
        'duplicate' => [$visit->id, $visit->id],
        'missing row' => [$visit->id, 999999],
        'text' => [$visit->id, 'abc'],
        'scalar' => $visit->id,
        'too many' => range(1, 101),
    };

    $this->postJson(route('bulk-visit-delete'), ['id' => $ids])->assertUnprocessable();
    expect(Visit::find($visit->id))->not->toBeNull();
})->with(['missing', 'empty', 'duplicate', 'missing row', 'text', 'scalar', 'too many']);

test('visit actions refuse callers without authorization and preserve every visit', function (string $caller, string $action) {
    $visit = storeAdminVisit();
    $original = $visit->getAttributes();
    actingAsRefusedStoreCaller($caller);

    $response = match ($action) {
        'index' => $this->get(route('admin.rep.visits.index')),
        'show' => $this->get(route('admin.rep.visits.show', $visit->id)),
        'day' => $this->get(route('admin.rep.visits.rep-show', $visit->rep_id)),
        'destroy' => $this->post(route('admin.rep.visits.destroy', $visit->id)),
        'bulk' => $this->post(route('bulk-visit-delete'), ['id' => [$visit->id]]),
    };

    assertStoreCallerRefused($response, $caller);
    expect($visit->fresh()->getAttributes())->toBe($original);
})->with('refused store admin callers')->with(['index', 'show', 'day', 'destroy', 'bulk']);

test('the visit read permission never grants delete and the delete permission never grants read', function () {
    $visit = storeAdminVisit();
    actingAsStoreUser(createStoreStaff('view_representative_visits'));
    $this->post(route('admin.rep.visits.destroy', $visit->id))->assertForbidden();
    $this->post(route('bulk-visit-delete'), ['id' => [$visit->id]])->assertForbidden();
    expect(Visit::find($visit->id))->not->toBeNull();

    actingAsStoreUser(createStoreStaff('delete_representative_visits'));
    $this->get(route('admin.rep.visits.index'))->assertForbidden();
    $this->get(route('admin.rep.visits.show', $visit->id))->assertForbidden();
    $this->get(route('admin.rep.visits.rep-show', $visit->rep_id))->assertForbidden();
});
