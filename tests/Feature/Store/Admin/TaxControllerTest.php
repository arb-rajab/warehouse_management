<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->refs = createStoreReferenceData();
});

test('the Taxes list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->get('/temp/admin/tax')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Taxes/Index'));
});

test('the Taxes list exposes exactly the narrow record', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->get('/temp/admin/tax')->assertOk()->assertInertia(fn (Assert $page) => $page->has('taxes.data', 1)->has('taxes.data.0', fn (Assert $row) => $row
        ->where('id', $this->refs['tax'])
        ->where('name', 'VAT')
        ->where('tax_status', true)
    ));
});

test('the Taxes list pages without overlaps and validates page size', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $row = (array) DB::table('taxes')->where('id', $this->refs['tax'])->first();
    unset($row['id']);
    foreach (range(1, 22) as $number) {
        $row['name'] = 'Record '.$number;
        DB::table('taxes')->insert($row);
    } assertPagesWithoutOverlap(fn (int $page) => $this->get('/temp/admin/tax?page='.$page), fn ($response) => collect($response->inertiaProps('taxes.data'))->pluck('id')->all(), 23, 20);
    $this->get('/temp/admin/tax?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('taxes.data', 10)->where('taxes.meta.per_page', 10));
    $this->getJson('/temp/admin/tax?per_page=13')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('the Taxes list refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/tax'), $caller);
})->with('refused store admin callers');

test('the Taxes edit screen can be rendered', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->get('/temp/admin/tax/'.$this->refs['tax'].'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/Taxes/Edit'));
});

test('the Taxes edit screen refuses callers', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/tax/'.$this->refs['tax'].'/edit'), $caller);
})->with('refused store admin callers');

test('the Taxes edit screen returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->get('/temp/admin/tax/999999/edit')->assertNotFound();
});

test('the Taxes store saves validated values', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->post('/temp/admin/tax', ['name' => 'Sales tax'])->assertRedirect(route('tax.index'));
    $this->assertDatabaseHas('taxes', ['name' => 'Sales tax']);
});

test('the Taxes store validates fields before writing', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->postJson('/temp/admin/tax', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseCount('taxes', 1);
});

test('the Taxes store refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/tax', ['name' => 'Sales tax']), $caller);
    $this->assertDatabaseCount('taxes', 1);
})->with('refused store admin callers');

test('the Taxes update saves validated values', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->put('/temp/admin/tax/'.$this->refs['tax'], ['name' => 'Sales tax'])->assertRedirect(route('tax.index'));
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'name' => 'Sales tax']);
});

test('the Taxes update validates fields without changing data', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->putJson('/temp/admin/tax/'.$this->refs['tax'], [])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'name' => 'VAT']);
});

test('the Taxes update refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put('/temp/admin/tax/'.$this->refs['tax'], ['name' => 'Sales tax']), $caller);
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'name' => 'VAT']);
})->with('refused store admin callers');

test('the Taxes update returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->put('/temp/admin/tax/999999', ['name' => 'Sales tax'])->assertNotFound();
});

test('the Taxes delete keeps the query and removes the record', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->delete('/temp/admin/tax/'.$this->refs['tax'].'?page=2&per_page=10')->assertRedirect(route('tax.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('taxes', ['id' => $this->refs['tax']]);
});

test('the Taxes delete refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->delete('/temp/admin/tax/'.$this->refs['tax']), $caller);
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax']]);
})->with('refused store admin callers');

test('the Taxes delete returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->delete('/temp/admin/tax/999999')->assertNotFound();
});

test('the Taxes status saves the posted value and keeps pagination', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->post('/temp/admin/tax/'.$this->refs['tax'].'/status'.'?page=2', ['status' => 0])->assertRedirect(route('tax.index', ['page' => 2]));
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'tax_status' => 0]);
});

test('the Taxes status validates booleans without writing', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->postJson('/temp/admin/tax/'.$this->refs['tax'].'/status', ['status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'tax_status' => 1]);
});

test('the Taxes status refuses writes', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post('/temp/admin/tax/'.$this->refs['tax'].'/status', ['status' => 0]), $caller);
    $this->assertDatabaseHas('taxes', ['id' => $this->refs['tax'], 'tax_status' => 1]);
})->with('refused store admin callers');

test('the Taxes status returns 404 for a missing id', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->post('/temp/admin/tax/999999/status', ['status' => 0])->assertNotFound();
});

test('the Taxes routes do not accept a WMS identity', function () {
    actingAsAdmin();
    $this->get('/temp/admin/tax')->assertRedirect(route('shop.login'));
    $this->post('/temp/admin/tax', ['name' => 'Sales tax'])->assertRedirect(route('shop.login'));
    $this->assertDatabaseCount('taxes', 1);
});
