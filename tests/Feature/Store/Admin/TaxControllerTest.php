<?php

use App\Models\Tax;
use Inertia\Testing\AssertableInertia as Assert;

test('taxes render, paginate and expose the complete narrow payload', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $lastId = null;
    foreach (range(1, 23) as $number) {
        $lastId = Tax::forceCreate(['name' => "Tax {$number}", 'tax_status' => true])->id;
    }

    $this->get('/temp/admin/tax?per_page=10')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/Taxes/Index')
            ->has('taxes.data', 10)
            ->has('taxes.data.0', fn (Assert $tax) => $tax
                ->where('id', $lastId)
                ->where('name', 'Tax 23')
                ->where('active', true))
            ->where('taxes.meta.total', 23)
    );
    $this->get('/temp/admin/tax?per_page=7')->assertSessionHasErrors('per_page');
});

test('taxes support validated CRUD, status changes and missing ids', function () {
    actingAsStoreUser(createStoreStaff('vat_&_tax_setup'));
    $this->post('/temp/admin/tax', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/temp/admin/tax', ['name' => 'VAT'])->assertRedirect(route('tax.index'));
    $tax = Tax::query()->where('name', 'VAT')->firstOrFail();
    $this->get("/temp/admin/tax/{$tax->id}/edit")->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Setup/Taxes/Edit')->where('tax.name', 'VAT')
    );
    $this->put("/temp/admin/tax/{$tax->id}", ['name' => 'BTW'])->assertRedirect(route('tax.index'));
    $this->patch("/temp/admin/tax/{$tax->id}/status", ['status' => false])->assertRedirect();
    $this->delete("/temp/admin/tax/{$tax->id}")->assertRedirect(route('tax.index'));
    $this->assertDatabaseMissing('taxes', ['id' => $tax->id]);

    $this->get('/temp/admin/tax/999999/edit')->assertNotFound();
    $this->put('/temp/admin/tax/999999', ['name' => 'X'])->assertNotFound();
    $this->delete('/temp/admin/tax/999999')->assertNotFound();
});

test('tax routes refuse callers without permission and writes change nothing', function (string $caller) {
    $tax = Tax::forceCreate(['name' => 'VAT']);
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get('/temp/admin/tax'), $caller);
    assertStoreCallerRefused($this->get("/temp/admin/tax/{$tax->id}/edit"), $caller);
    assertStoreCallerRefused($this->post('/temp/admin/tax', ['name' => 'Nope']), $caller);
    assertStoreCallerRefused($this->put("/temp/admin/tax/{$tax->id}", ['name' => 'Nope']), $caller);
    assertStoreCallerRefused($this->patch("/temp/admin/tax/{$tax->id}/status", ['status' => false]), $caller);
    assertStoreCallerRefused($this->delete("/temp/admin/tax/{$tax->id}"), $caller);
    $this->assertDatabaseHas('taxes', ['id' => $tax->id, 'name' => 'VAT']);
    $this->assertDatabaseMissing('taxes', ['name' => 'Nope']);
})->with('refused store admin callers');
