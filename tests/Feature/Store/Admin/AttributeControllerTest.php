<?php

use App\Models\Attribute;
use App\Models\AttributeTranslation;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function catalogueAttribute(string $name = 'Size'): Attribute
{
    return Attribute::create(['name' => $name]);
}

test('attributes render narrow translated rows with their values', function () {
    $attribute = catalogueAttribute();
    AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => 'en', 'name' => 'Dimensions']);
    $value = AttributeValue::create(['attribute_id' => $attribute->id, 'value' => 'Large', 'color_code' => '#secret']);
    actingAsStoreUser(createStoreStaff('view_product_attributes'));

    $this->get('/temp/admin/attributes')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Attributes/Index')
        ->where('filters', ['per_page' => 20])
        ->has('attributes.data', 1)
        ->has('attributes.data.0', fn (Assert $row) => $row
            ->where('id', $attribute->id)->where('name', 'Dimensions')
            ->where('values', [['id' => $value->id, 'attribute_id' => $attribute->id, 'value' => 'Large']])));
});

test('attributes paginate and validate the page size', function () {
    foreach (range(1, 23) as $number) {
        catalogueAttribute("Size $number");
    }
    actingAsStoreUser(createStoreStaff('view_product_attributes'));
    $first = $this->get('/temp/admin/attributes')->assertOk()->inertiaProps('attributes');
    expect($first['data'])->toHaveCount(20)->and($first['meta']['total'])->toBe(23);
    $this->get('/temp/admin/attributes?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10)->has('attributes.data', 10));
    $this->get('/temp/admin/attributes?per_page=7')->assertSessionHasErrors('per_page');
});

test('attributes create the default translation and reject invalid names', function () {
    actingAsStoreUser(createStoreStaff('edit_product_attribute'));
    $this->post('/temp/admin/attributes', ['name' => 'Size'])->assertRedirect(route('attributes.index'));
    $attribute = Attribute::query()->firstOrFail();
    $this->assertDatabaseHas('attribute_translations', ['attribute_id' => $attribute->id, 'lang' => 'en', 'name' => 'Size']);
    foreach (['', str_repeat('x', 51), ['invalid']] as $name) {
        $this->post('/temp/admin/attributes', ['name' => $name])->assertSessionHasErrors('name');
    }
    $this->assertDatabaseCount('attributes', 1);
});

test('attribute editing renders its selected translation and languages', function () {
    $attribute = catalogueAttribute();
    DB::table('languages')->insert([['code' => 'en', 'name' => 'English', 'rtl' => 0], ['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]]);
    AttributeTranslation::create(['attribute_id' => $attribute->id, 'lang' => 'ar', 'name' => 'المقاس']);
    actingAsStoreUser(createStoreStaff('edit_product_attribute'));
    $this->get("/temp/admin/attributes/edit/{$attribute->id}?lang=ar")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Attributes/Edit')
        ->where('attribute', ['id' => $attribute->id, 'name' => 'Size'])
        ->where('name', 'المقاس')->where('lang', 'ar')
        ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'Arabic']]));
    $this->get("/temp/admin/attributes/edit/{$attribute->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('name', 'Size')->where('lang', 'en'));
    $this->get("/temp/admin/attributes/edit/{$attribute->id}?lang=xx")->assertSessionHasErrors('lang');
});

test('attribute updates preserve base content outside its default language', function () {
    $attribute = catalogueAttribute();
    DB::table('languages')->insert(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    actingAsStoreUser(createStoreStaff('edit_product_attribute'));
    $this->put("/temp/admin/attributes/{$attribute->id}", ['name' => 'المقاس', 'lang' => 'ar'])->assertRedirect(route('attributes.index'));
    $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'Size']);
    $this->assertDatabaseHas('attribute_translations', ['attribute_id' => $attribute->id, 'lang' => 'ar', 'name' => 'المقاس']);
    $this->put("/temp/admin/attributes/{$attribute->id}", ['name' => 'Dimensions', 'lang' => 'en'])->assertRedirect(route('attributes.index'));
    $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'Dimensions']);
    $this->assertDatabaseHas('attribute_translations', ['attribute_id' => $attribute->id, 'lang' => 'en', 'name' => 'Dimensions']);
    foreach ([['name' => '', 'lang' => 'en'], ['name' => str_repeat('x', 51), 'lang' => 'en'], ['name' => 'Bad', 'lang' => 'xx'], ['name' => 'Bad']] as $invalid) {
        $this->put("/temp/admin/attributes/{$attribute->id}", $invalid)->assertSessionHasErrors();
    }
    $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'Dimensions']);
});

test('attribute deletion removes only its translations and preserves pagination', function () {
    $attribute = catalogueAttribute();
    $other = catalogueAttribute('Material');
    foreach ([$attribute, $other] as $record) {
        AttributeTranslation::create(['attribute_id' => $record->id, 'lang' => 'en', 'name' => $record->name]);
    }
    actingAsStoreUser(createStoreStaff('delete_product_attribute'));
    $this->delete("/temp/admin/attributes/destroy/{$attribute->id}?page=2&per_page=10")->assertRedirect(route('attributes.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('attributes', ['id' => $attribute->id]);
    $this->assertDatabaseMissing('attribute_translations', ['attribute_id' => $attribute->id]);
    $this->assertDatabaseHas('attribute_translations', ['attribute_id' => $other->id]);
});

test('attribute record actions return 404 for missing records', function (string $action) {
    actingAsStoreUser(createStoreStaff('edit_product_attribute', 'delete_product_attribute'));
    (match ($action) {
        'edit' => $this->get('/temp/admin/attributes/edit/999999'),
        'update' => $this->put('/temp/admin/attributes/999999', ['name' => 'Ghost', 'lang' => 'en']),
        'destroy' => $this->delete('/temp/admin/attributes/destroy/999999'),
    })->assertNotFound();
})->with(['edit', 'update', 'destroy']);

test('every attribute action refuses unauthorized store callers and leaves content unchanged', function (string $caller, string $action) {
    $attribute = catalogueAttribute();
    actingAsRefusedStoreCaller($caller);
    $response = match ($action) {
        'index' => $this->get('/temp/admin/attributes'),
        'store' => $this->post('/temp/admin/attributes', ['name' => 'Nope']),
        'edit' => $this->get("/temp/admin/attributes/edit/{$attribute->id}"),
        'update' => $this->put("/temp/admin/attributes/{$attribute->id}", ['name' => 'Nope', 'lang' => 'en']),
        'destroy' => $this->delete("/temp/admin/attributes/destroy/{$attribute->id}"),
    };
    assertStoreCallerRefused($response, $caller);
    $this->assertDatabaseCount('attributes', 1);
    $this->assertDatabaseHas('attributes', ['id' => $attribute->id, 'name' => 'Size']);
    $this->assertDatabaseCount('attribute_translations', 0);
})->with('refused store admin callers')->with(['index', 'store', 'edit', 'update', 'destroy']);
