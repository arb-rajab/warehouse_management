<?php

use App\Models\Color;
use Inertia\Testing\AssertableInertia as Assert;

function catalogueColor(string $name = 'Red', string $code = '#ff0000'): Color
{
    $color = new Color;
    $color->name = $name;
    $color->code = $code;
    $color->save();

    return $color;
}

test('colors render narrow rows and search only matching names', function () {
    $color = catalogueColor();
    catalogueColor('Blue', '#0000ff');
    actingAsStoreUser(createStoreStaff('view_colors'));
    $this->get('/temp/admin/colors?search=Red')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Colors/Index')->where('filters', ['search' => 'Red', 'per_page' => 20])
        ->has('colors.data', 1)
        ->has('colors.data.0', fn (Assert $row) => $row->where('id', $color->id)->where('name', 'Red')->where('code', '#ff0000')));
    $this->get('/temp/admin/colors?search='.str_repeat('x', 256))->assertSessionHasErrors('search');
});

test('colors paginate and validate the page size', function () {
    foreach (range(1, 23) as $number) {
        catalogueColor("Color $number", sprintf('#%06x', $number));
    }
    actingAsStoreUser(createStoreStaff('view_colors'));
    $first = $this->get('/temp/admin/colors')->assertOk()->inertiaProps('colors');
    expect($first['data'])->toHaveCount(20)->and($first['meta']['total'])->toBe(23);
    $this->get('/temp/admin/colors?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10)->has('colors.data', 10));
    $this->get('/temp/admin/colors?per_page=7')->assertSessionHasErrors('per_page');
});

test('colors normalize spaces and enforce the database widths and unique codes', function () {
    actingAsStoreUser(createStoreStaff('edit_color'));
    $this->post('/temp/admin/colors/store', ['name' => 'Light Red', 'code' => '#ff0000'])->assertRedirect(route('colors'));
    $this->assertDatabaseHas('colors', ['name' => 'LightRed', 'code' => '#ff0000']);
    foreach ([['name' => '', 'code' => '#abc'], ['name' => str_repeat('x', 31), 'code' => '#abc'], ['name' => 'Blue', 'code' => ''], ['name' => 'Blue', 'code' => str_repeat('x', 11)], ['name' => 'Duplicate', 'code' => '#ff0000']] as $invalid) {
        $this->post('/temp/admin/colors/store', $invalid)->assertSessionHasErrors();
    }
    $this->assertDatabaseCount('colors', 1);
});

test('color edit renders narrow props', function () {
    $color = catalogueColor();
    actingAsStoreUser(createStoreStaff('edit_color'));
    $this->get("/temp/admin/colors/edit/{$color->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Product/Colors/Edit')->where('color', ['id' => $color->id, 'name' => 'Red', 'code' => '#ff0000']));
});

test('colors update their own unique code and reject invalid writes', function () {
    $color = catalogueColor();
    catalogueColor('Blue', '#0000ff');
    actingAsStoreUser(createStoreStaff('edit_color'));
    $this->put("/temp/admin/colors/update/{$color->id}", ['name' => 'Light Red', 'code' => '#ff0000'])->assertRedirect(route('colors'));
    $this->assertDatabaseHas('colors', ['id' => $color->id, 'name' => 'LightRed', 'code' => '#ff0000']);
    foreach ([['name' => '', 'code' => '#abc'], ['name' => str_repeat('x', 31), 'code' => '#abc'], ['name' => 'Red', 'code' => ''], ['name' => 'Red', 'code' => str_repeat('x', 11)], ['name' => 'Red', 'code' => '#0000ff']] as $invalid) {
        $this->put("/temp/admin/colors/update/{$color->id}", $invalid)->assertSessionHasErrors();
    }
    $this->assertDatabaseHas('colors', ['id' => $color->id, 'name' => 'LightRed', 'code' => '#ff0000']);
});

test('color deletion preserves search pagination and unrelated rows', function () {
    $color = catalogueColor();
    $other = catalogueColor('Blue', '#0000ff');
    actingAsStoreUser(createStoreStaff('delete_color'));
    $this->delete("/temp/admin/colors/destroy/{$color->id}?search=Red&page=2&per_page=10")->assertRedirect(route('colors', ['search' => 'Red', 'page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('colors', ['id' => $color->id]);
    $this->assertDatabaseHas('colors', ['id' => $other->id]);
});

test('color record actions return 404 for missing records', function (string $action) {
    actingAsStoreUser(createStoreStaff('edit_color', 'delete_color'));
    (match ($action) {
        'edit' => $this->get('/temp/admin/colors/edit/999999'),
        'update' => $this->put('/temp/admin/colors/update/999999', ['name' => 'Ghost', 'code' => '#abc']),
        'destroy' => $this->delete('/temp/admin/colors/destroy/999999'),
    })->assertNotFound();
})->with(['edit', 'update', 'destroy']);

test('every color action refuses unauthorized callers and preserves data', function (string $caller, string $action) {
    $color = catalogueColor();
    actingAsRefusedStoreCaller($caller);
    $response = match ($action) {
        'index' => $this->get('/temp/admin/colors'),
        'store' => $this->post('/temp/admin/colors/store', ['name' => 'Nope', 'code' => '#abc']),
        'edit' => $this->get("/temp/admin/colors/edit/{$color->id}"),
        'update' => $this->put("/temp/admin/colors/update/{$color->id}", ['name' => 'Nope', 'code' => '#abc']),
        'destroy' => $this->delete("/temp/admin/colors/destroy/{$color->id}"),
    };
    assertStoreCallerRefused($response, $caller);
    $this->assertDatabaseCount('colors', 1);
    $this->assertDatabaseHas('colors', ['id' => $color->id, 'name' => 'Red', 'code' => '#ff0000']);
})->with('refused store admin callers')->with(['index', 'store', 'edit', 'update', 'destroy']);
