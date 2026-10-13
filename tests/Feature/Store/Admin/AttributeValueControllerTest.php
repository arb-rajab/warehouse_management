<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use Inertia\Testing\AssertableInertia as Assert;

function catalogueValueParent(string $name = 'Size'): Attribute
{
    return Attribute::create(['name' => $name]);
}

function catalogueValue(Attribute $attribute, string $value = 'Large'): AttributeValue
{
    return AttributeValue::create(['attribute_id' => $attribute->id, 'value' => $value, 'color_code' => '#123456']);
}

test('attribute values render narrow rows and exclude unrelated attributes', function () {
    $attribute = catalogueValueParent();
    $value = catalogueValue($attribute);
    $noise = catalogueValue(catalogueValueParent('Material'), 'Cotton');
    actingAsStoreUser(createStoreStaff('view_product_attribute_values'));
    $this->get("/temp/admin/attributes/{$attribute->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/AttributeValues/Index')
        ->where('attribute', ['id' => $attribute->id, 'name' => 'Size'])
        ->where('filters', ['per_page' => 20])->has('values.data', 1)
        ->has('values.data.0', fn (Assert $row) => $row->where('id', $value->id)->where('attribute_id', $attribute->id)->where('value', 'Large')));
});

test('attribute values paginate and validate the page size', function () {
    $attribute = catalogueValueParent();
    foreach (range(1, 23) as $number) {
        catalogueValue($attribute, "Size $number");
    }
    actingAsStoreUser(createStoreStaff('view_product_attribute_values'));
    $first = $this->get("/temp/admin/attributes/{$attribute->id}")->assertOk()->inertiaProps('values');
    expect($first['data'])->toHaveCount(20)->and($first['meta']['total'])->toBe(23);
    $this->get("/temp/admin/attributes/{$attribute->id}?per_page=10")->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10)->has('values.data', 10));
    $this->get("/temp/admin/attributes/{$attribute->id}?per_page=7")->assertSessionHasErrors('per_page');
});

test('attribute values preserve capitalization and validate attribute references', function () {
    $attribute = catalogueValueParent();
    actingAsStoreUser(createStoreStaff('edit_product_attribute_value'));
    $this->post('/temp/admin/store-attribute-value', ['attribute_id' => $attribute->id, 'value' => 'large'])->assertRedirect(route('attributes.show', $attribute->id));
    $this->assertDatabaseHas('attribute_values', ['attribute_id' => $attribute->id, 'value' => 'Large']);
    foreach ([['attribute_id' => 999999, 'value' => 'Small'], ['attribute_id' => $attribute->id, 'value' => ''], ['attribute_id' => $attribute->id, 'value' => str_repeat('x', 256)], ['value' => 'Small']] as $invalid) {
        $this->post('/temp/admin/store-attribute-value', $invalid)->assertSessionHasErrors();
    }
    $this->assertDatabaseCount('attribute_values', 1);
});

test('attribute value edit renders a narrow record', function () {
    $attribute = catalogueValueParent();
    $value = catalogueValue($attribute);
    actingAsStoreUser(createStoreStaff('edit_product_attribute_value'));
    $this->get("/temp/admin/edit-attribute-value/{$value->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/AttributeValues/Edit')
        ->where('value', ['id' => $value->id, 'attribute_id' => $attribute->id, 'value' => 'Large']));
});

test('attribute value updates preserve extra fields and validate references', function () {
    $attribute = catalogueValueParent();
    $value = catalogueValue($attribute);
    $other = catalogueValueParent('Material');
    actingAsStoreUser(createStoreStaff('edit_product_attribute_value'));
    $this->put("/temp/admin/update-attribute-value/{$value->id}", ['attribute_id' => $other->id, 'value' => 'cotton'])->assertRedirect(route('attributes.show', $other->id));
    $this->assertDatabaseHas('attribute_values', ['id' => $value->id, 'attribute_id' => $other->id, 'value' => 'Cotton', 'color_code' => '#123456']);
    foreach ([['attribute_id' => 999999, 'value' => 'Bad'], ['attribute_id' => $other->id, 'value' => ''], ['attribute_id' => $other->id, 'value' => str_repeat('x', 256)]] as $invalid) {
        $this->put("/temp/admin/update-attribute-value/{$value->id}", $invalid)->assertSessionHasErrors();
    }
    $this->assertDatabaseHas('attribute_values', ['id' => $value->id, 'value' => 'Cotton']);
});

test('attribute value deletes preserve parent and pagination', function () {
    $attribute = catalogueValueParent();
    $value = catalogueValue($attribute);
    $other = catalogueValue($attribute, 'Small');
    actingAsStoreUser(createStoreStaff('delete_product_attribute_value'));
    $this->delete("/temp/admin/destroy-attribute-value/{$value->id}?page=2&per_page=10")->assertRedirect(route('attributes.show', ['attribute' => $attribute->id, 'page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('attribute_values', ['id' => $value->id]);
    $this->assertDatabaseHas('attribute_values', ['id' => $other->id]);
    $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
});

test('attribute value record actions return 404 for missing records', function (string $action) {
    actingAsStoreUser(createStoreStaff('view_product_attribute_values', 'edit_product_attribute_value', 'delete_product_attribute_value'));
    (match ($action) {
        'index' => $this->get('/temp/admin/attributes/999999'),
        'edit' => $this->get('/temp/admin/edit-attribute-value/999999'),
        'update' => $this->put('/temp/admin/update-attribute-value/999999', ['attribute_id' => 999999, 'value' => 'Ghost']),
        'destroy' => $this->delete('/temp/admin/destroy-attribute-value/999999'),
    })->assertNotFound();
})->with(['index', 'edit', 'update', 'destroy']);

test('every attribute value action refuses unauthorized callers and preserves data', function (string $caller, string $action) {
    $attribute = catalogueValueParent();
    $value = catalogueValue($attribute);
    actingAsRefusedStoreCaller($caller);
    $response = match ($action) {
        'index' => $this->get("/temp/admin/attributes/{$attribute->id}"),
        'store' => $this->post('/temp/admin/store-attribute-value', ['attribute_id' => $attribute->id, 'value' => 'Nope']),
        'edit' => $this->get("/temp/admin/edit-attribute-value/{$value->id}"),
        'update' => $this->put("/temp/admin/update-attribute-value/{$value->id}", ['attribute_id' => $attribute->id, 'value' => 'Nope']),
        'destroy' => $this->delete("/temp/admin/destroy-attribute-value/{$value->id}"),
    };
    assertStoreCallerRefused($response, $caller);
    $this->assertDatabaseCount('attribute_values', 1);
    $this->assertDatabaseHas('attribute_values', ['id' => $value->id, 'attribute_id' => $attribute->id, 'value' => 'Large', 'color_code' => '#123456']);
})->with('refused store admin callers')->with(['index', 'store', 'edit', 'update', 'destroy']);
