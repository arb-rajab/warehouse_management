<?php

use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use ArPHP\I18N\Arabic;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser as PdfParser;

test('an authenticated user can view the row list with every property the table renders', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 3, 'flats_count' => 2]);

    $response = $this->get('/admin/rows');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Index')
            ->has('rows.data', 1)
            ->has('rows.data.0', fn (Assert $rowProp) => $rowProp
                ->where('id', $row->id)
                ->where('letter', 'Z')
                ->where('cells_count', 3)
                ->where('flats_count', 2)
                ->where('has_pallets', false)
            )
    );
});

test('the row list flags rows that have pallets in them', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    Pallet::factory()->create(['cell_id' => $row->cells()->first()->id]);

    $response = $this->get('/admin/rows');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Index')
            ->where('rows.data.0.has_pallets', true)
    );
});

test('the row list paginates instead of returning everything at once', function () {
    actingAsAdmin();
    Row::factory()->count(25)->create();

    $response = $this->get('/admin/rows');

    assertInertiaPaginates($response, 'rows', 20, 25, 'Admin/Rows/Index');
});

test('the row list respects a per_page query parameter', function () {
    actingAsAdmin();
    Row::factory()->count(25)->create();

    $response = $this->get('/admin/rows?per_page=10');

    assertInertiaPaginates($response, 'rows', 10, 25, 'Admin/Rows/Index');
    $response->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10));
});

test('an out-of-range per_page value falls back to the default page size', function () {
    actingAsAdmin();
    Row::factory()->count(25)->create();

    $response = $this->get('/admin/rows?per_page=999');

    assertInertiaPaginates($response, 'rows', 20, 25, 'Admin/Rows/Index');
});

test('a mobile app user cannot view the row list', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/rows');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the row list', function () {
    $response = $this->get('/admin/rows');

    $response->assertRedirect(route('login'));
});

test('an authenticated user can view the create row page', function () {
    actingAsAdmin();

    $response = $this->get('/admin/rows/create');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Create')
    );
});

test('a mobile app user cannot view the create row page', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/rows/create');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the create row page', function () {
    $response = $this->get('/admin/rows/create');

    $response->assertRedirect(route('login'));
});

test('an authenticated user can create a row and its cells are generated', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
    ]);

    $row = Row::query()->where('letter', 'Z')->first();

    $response->assertRedirect(route('admin.rows.show', $row));
    expect($row)->not->toBeNull();
    expect($row->cells()->count())->toBe(6);
});

test('creating a row normalizes a lowercase letter to uppercase', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'z',
        'cells_count' => 3,
        'flats_count' => 2,
    ]);

    $row = Row::query()->where('letter', 'Z')->first();

    $response->assertRedirect(route('admin.rows.show', $row));
    expect($row)->not->toBeNull();
});

test('creating a row with a duplicate letter is rejected and nothing changes', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'Z']);

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
    ]);

    $response->assertSessionHasErrors('letter');
    $this->assertDatabaseCount('rows', 1);
});

test('creating a row with invalid dimensions is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 0,
        'flats_count' => 2,
    ]);

    $response->assertSessionHasErrors('cells_count');
    $this->assertDatabaseCount('rows', 0);
});

test('creating a row with an invalid flats_count is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 2,
        'flats_count' => 0,
    ]);

    $response->assertSessionHasErrors('flats_count');
    $this->assertDatabaseCount('rows', 0);
});

test('creating a row with a cells_count over the operational maximum is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => Row::MAX_DIMENSION + 1,
        'flats_count' => 2,
    ]);

    $response->assertSessionHasErrors('cells_count');
    $this->assertDatabaseCount('rows', 0);
    $this->assertDatabaseCount('cells', 0);
});

test('creating a row with a flats_count over the operational maximum is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 2,
        'flats_count' => Row::MAX_DIMENSION + 1,
    ]);

    $response->assertSessionHasErrors('flats_count');
    $this->assertDatabaseCount('rows', 0);
    $this->assertDatabaseCount('cells', 0);
});

test('creating a row at exactly the operational maximum dimensions succeeds', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => Row::MAX_DIMENSION,
        'flats_count' => 1,
    ]);

    $row = Row::query()->where('letter', 'Z')->first();

    $response->assertRedirect(route('admin.rows.show', $row));
    expect($row)->not->toBeNull();
    expect($row->cells()->count())->toBe(Row::MAX_DIMENSION);
});

test('creating a row with a letter longer than 2 characters is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/rows', [
        'letter' => 'ABC',
        'cells_count' => 2,
        'flats_count' => 2,
    ]);

    $response->assertSessionHasErrors('letter');
    $this->assertDatabaseCount('rows', 0);
});

test('a mobile app user cannot create a row and nothing changes', function () {
    actingAsMobilePanelUser();

    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('rows', 0);
});

test('an unauthenticated caller cannot create a row and nothing changes', function () {
    $response = $this->post('/admin/rows', [
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('rows', 0);
});

test('an authenticated user can view a rows cell grid, including pallet and added_at for occupied cells', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'C', 'cells_count' => 2, 'flats_count' => 1]);

    $product = Product::factory()->imageUrl('https://cdn.example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $occupiedCell = $row->cells()->where('cell_number', 1)->first();
    $emptyCell = $row->cells()->where('cell_number', 2)->first();
    $pallet = Pallet::factory()->create(['cell_id' => $occupiedCell->id, 'product_id' => $product->id]);

    $response = $this->get("/admin/rows/{$row->letter}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Show')
            ->has('row', fn (Assert $rowProp) => $rowProp
                ->where('id', $row->id)
                ->where('letter', 'B')
                ->where('cells_count', 2)
                ->where('flats_count', 1)
                ->where('has_pallets', true)
            )
            ->has('rows', 2)
            ->where('rows.0.letter', 'B')
            ->where('rows.1.letter', 'C')
            ->has('cells', 2)
            ->has('cells.0', fn (Assert $cell) => $cell
                ->where('id', $occupiedCell->id)
                ->where('cell_number', 1)
                ->where('flat_number', 1)
                ->where('state', 'full')
                ->where('is_active', true)
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('product_id', $product->id)
                    ->where('product_name', 'Widgets')
                    ->where('product_ar_name', 'ودجات')
                    ->where('product_active', true)
                    ->where('product_image_url', 'https://cdn.example.com/widgets.png')
                    ->where('expiration_date', $pallet->expiration_date->toDateString())
                    ->where('added_at', $pallet->created_at->toIso8601String())
                    ->where('cell_entered_at', null)
                    ->where('is_stale', null)
                    ->where('remaining_boxes', $pallet->remaining_boxes)
                )
            )
            ->has('cells.1', fn (Assert $cell) => $cell
                ->where('id', $emptyCell->id)
                ->where('cell_number', 2)
                ->where('flat_number', 1)
                ->where('state', 'empty')
                ->where('is_active', true)
                ->where('pallet', null)
            )
    );

    expect($otherRow->id)->not->toBeNull();
});

test("a rows cell grid exposes today's date", function () {
    Carbon::setTestNow('2026-08-13 10:00:00');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->get("/admin/rows/{$row->letter}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('today', '2026-08-13')
    );

    Carbon::setTestNow();
});

test('a rows cell grid has no pre-selected products for the highlight filter, since it never has an initial selection', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z']);
    Product::factory()->create(['name' => 'Widgets']);

    $response = $this->get("/admin/rows/{$row->letter}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('filterOptions.products', 0)
    );
});

test('a mobile app user cannot view a rows cell grid', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->get("/admin/rows/{$row->letter}");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing a rows cell grid', function () {
    $row = Row::factory()->create();

    $response = $this->get("/admin/rows/{$row->letter}");

    $response->assertRedirect(route('login'));
});

test('an authenticated user can view the edit row page with every property the form needs', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->get("/admin/rows/{$row->letter}/edit");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Rows/Edit')
            ->has('row', fn (Assert $rowProp) => $rowProp
                ->where('id', $row->id)
                ->where('letter', 'B')
                ->where('cells_count', 2)
                ->where('flats_count', 1)
                ->where('has_pallets', false)
            )
    );
});

test('a mobile app user cannot view the edit row page', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->get("/admin/rows/{$row->letter}/edit");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the edit row page', function () {
    $row = Row::factory()->create();

    $response = $this->get("/admin/rows/{$row->letter}/edit");

    $response->assertRedirect(route('login'));
});

test('viewing the edit page for a non-existent row returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/rows/ZZ/edit');

    $response->assertNotFound();
});

test('renaming a rows letter always succeeds', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cellIds = $row->cells()->pluck('id');

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => $row->cells_count,
        'flats_count' => $row->flats_count,
    ]);

    $response->assertRedirect(route('admin.rows.show', $row->fresh()));
    expect($row->fresh()->letter)->toBe('Y');
    expect($row->cells()->pluck('id'))->toEqual($cellIds);
});

test('renaming a row normalizes a lowercase letter to uppercase', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'y',
        'cells_count' => $row->cells_count,
        'flats_count' => $row->flats_count,
    ]);

    $response->assertRedirect(route('admin.rows.show', $row->fresh()));
    expect($row->fresh()->letter)->toBe('Y');
});

test('renaming a row to a letter that already exists is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'Y']);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => $row->cells_count,
        'flats_count' => $row->flats_count,
    ]);

    $response->assertSessionHasErrors('letter');
    expect($row->fresh()->letter)->toBe('Z');
    expect($otherRow->fresh()->letter)->toBe('Y');
});

test('renaming a row holding a pallet leaves its cells and the pallet untouched', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cellIds = $row->cells()->pluck('id');
    $cell = $row->cells()->first();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => $row->cells_count,
        'flats_count' => $row->flats_count,
    ]);

    $response->assertRedirect(route('admin.rows.show', $row->fresh()));
    expect($row->fresh()->letter)->toBe('Y');
    expect($row->cells()->pluck('id'))->toEqual($cellIds);
    expect($pallet->fresh()->cell_id)->toBe($cell->id);
});

test('renaming a row holding history but no pallet leaves its cells untouched', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cellIds = $row->cells()->pluck('id');
    $cell = $row->cells()->first();
    $log = CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => $row->cells_count,
        'flats_count' => $row->flats_count,
    ]);

    $response->assertRedirect(route('admin.rows.show', $row->fresh()));
    expect($row->fresh()->letter)->toBe('Y');
    expect($row->cells()->pluck('id'))->toEqual($cellIds);
    expect($log->fresh()->cell_id)->toBe($cell->id);
});

test('resizing a row with no pallets regenerates its cells', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => 4,
        'flats_count' => 3,
    ]);

    $response->assertRedirect(route('admin.rows.show', $row->fresh()));
    expect($row->cells()->count())->toBe(12);
});

test('resizing a row that has a pallet is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => 5,
        'flats_count' => 5,
    ]);

    $response->assertRedirect(route('admin.rows.edit', $row));
    $response->assertSessionHasErrors(['cells_count' => __('messages.row_cannot_resize_has_pallets')]);
    expect($row->fresh()->cells_count)->toBe(2);
    expect($row->fresh()->flats_count)->toBe(1);
    expect($row->cells()->count())->toBe(2);
});

test('resizing a row that has history but no pallet is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => 5,
        'flats_count' => 5,
    ]);

    $response->assertRedirect(route('admin.rows.edit', $row));
    $response->assertSessionHasErrors(['cells_count' => __('messages.row_cannot_resize_has_history')]);
    expect($row->fresh()->cells_count)->toBe(2);
    expect($row->fresh()->flats_count)->toBe(1);
    expect($row->cells()->count())->toBe(2);
    $this->assertDatabaseHas('cells', ['id' => $cell->id]);
});

test('resizing a row with invalid dimensions and an existing pallet reports the dimension error, not the pallet block', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => 0,
        'flats_count' => 1,
    ]);

    $response->assertSessionHasErrors('cells_count');
    expect(session('errors')->get('cells_count'))->not->toContain(__('messages.row_cannot_resize_has_pallets'));
    expect($row->fresh()->cells_count)->toBe(2);
});

test('updating a row with an invalid flats_count is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => 2,
        'flats_count' => 0,
    ]);

    $response->assertSessionHasErrors('flats_count');
    expect($row->fresh()->flats_count)->toBe(1);
});

test('updating a row with a cells_count over the operational maximum is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Z',
        'cells_count' => Row::MAX_DIMENSION + 1,
        'flats_count' => 1,
    ]);

    $response->assertSessionHasErrors('cells_count');
    expect($row->fresh()->cells_count)->toBe(2);
});

test('updating a row with a letter longer than 2 characters is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'ABC',
        'cells_count' => 2,
        'flats_count' => 1,
    ]);

    $response->assertSessionHasErrors('letter');
    expect($row->fresh()->letter)->toBe('Z');
});

test('a mobile app user cannot update a row and nothing changes', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => 4,
        'flats_count' => 3,
    ]);

    $response->assertForbidden();
    expect($row->fresh()->letter)->toBe('Z');
    expect($row->fresh()->cells_count)->toBe(2);
});

test('an unauthenticated caller cannot update a row and nothing changes', function () {
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->put("/admin/rows/{$row->letter}", [
        'letter' => 'Y',
        'cells_count' => 2,
        'flats_count' => 1,
    ]);

    $response->assertRedirect(route('login'));
    expect($row->fresh()->letter)->toBe('Z');
});

test('updating a non-existent row returns a 404', function () {
    actingAsAdmin();

    $response = $this->put('/admin/rows/ZZ', [
        'letter' => 'Y',
        'cells_count' => 2,
        'flats_count' => 1,
    ]);

    $response->assertNotFound();
});

test('an admin can delete a row with no pallets in it', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'Y']);

    $response = $this->delete("/admin/rows/{$row->letter}");

    $response->assertRedirect(route('admin.rows.index'));
    $this->assertDatabaseMissing('rows', ['id' => $row->id]);
    $this->assertDatabaseMissing('cells', ['row_id' => $row->id]);
    $this->assertDatabaseHas('rows', ['id' => $otherRow->id]);
});

test('deleting a row redirects back with the current page preserved', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->delete("/admin/rows/{$row->letter}?page=2");

    $response->assertRedirect(route('admin.rows.index', ['page' => 2]));
});

test('deleting a row that has a pallet is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->delete("/admin/rows/{$row->letter}");

    $response->assertRedirect(route('admin.rows.index'));
    $response->assertSessionHasErrors(['row' => __('messages.row_cannot_delete_has_pallets')]);
    $this->assertDatabaseHas('rows', ['id' => $row->id]);
    expect($row->cells()->count())->toBe(2);
});

test('deleting a row that has history but no pallet is rejected and nothing changes', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    $response = $this->delete("/admin/rows/{$row->letter}");

    $response->assertRedirect(route('admin.rows.index'));
    $response->assertSessionHasErrors(['row' => __('messages.row_cannot_delete_has_history')]);
    $this->assertDatabaseHas('rows', ['id' => $row->id]);
    expect($row->cells()->count())->toBe(2);
    $this->assertDatabaseHas('cells', ['id' => $cell->id]);
});

test('a rejected row deletion redirects back with the current page preserved', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    Pallet::factory()->create(['cell_id' => $cell->id]);

    $response = $this->delete("/admin/rows/{$row->letter}?page=2");

    $response->assertRedirect(route('admin.rows.index', ['page' => 2]));
    $response->assertSessionHasErrors(['row' => __('messages.row_cannot_delete_has_pallets')]);
});

test('a mobile app user cannot delete a row and nothing changes', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 1]);

    $response = $this->delete("/admin/rows/{$row->letter}");

    $response->assertForbidden();
    $this->assertDatabaseHas('rows', ['id' => $row->id]);
    expect($row->cells()->count())->toBe(2);
});

test('an unauthenticated caller cannot delete a row and nothing changes', function () {
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->delete("/admin/rows/{$row->letter}");

    $response->assertRedirect(route('login'));
    $this->assertDatabaseHas('rows', ['id' => $row->id]);
});

test('deleting a non-existent row returns a 404', function () {
    actingAsAdmin();

    $response = $this->delete('/admin/rows/ZZ');

    $response->assertNotFound();
});

test('an authenticated user can export QR codes for every cell in a row', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 2, 'flats_count' => 2]);

    $response = $this->get("/admin/rows/{$row->letter}/export-qr-codes");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    // A PDF with 4 real QR codes drawn is meaningfully larger than one with just
    // labels (~1.1KB) — regression guard for the QR silently failing to render
    // (dompdf doesn't support inline <svg>, only an <img> referencing an image source).
    expect(strlen($response->getContent()))->toBeGreaterThan(2500);

    $pdfText = (new PdfParser)->parseContent($response->getContent())->getText();
    foreach ($row->cells()->orderedByCoordinates()->get() as $cell) {
        expect($pdfText)->toContain(Cell::slotLabel($row->letter, $cell->cell_number, $cell->flat_number));
    }
});

test('exporting QR codes for a row in Arabic renders properly shaped RTL description text', function () {
    app()->setLocale('ar');
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/rows/{$row->letter}/export-qr-codes");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $rawDescription = __('messages.qr_label_description', [
        'row' => $row->letter,
        'cell' => $cell->cell_number,
        'flat' => $cell->flat_number,
    ]);
    // Mirrors BuildsCellQrLabels::shapeArabicForPdf() — dompdf has no Arabic
    // shaping/bidi engine of its own, so the trait pre-shapes the string into
    // joined presentation-form glyphs in final display order before dompdf
    // ever sees it.
    $shapedDescription = (new Arabic)->utf8Glyphs($rawDescription, max_chars: 1000, hindo: false, forcertl: true);

    $pdfText = (new PdfParser)->parseContent($response->getContent())->getText();
    expect($pdfText)->toContain($shapedDescription);
    // Regression guard: the un-shaped translation string must never reach the
    // PDF verbatim — that produces disconnected, logical-order Arabic letters.
    expect($pdfText)->not->toContain($rawDescription);
});

test('a mobile app user cannot export QR codes for a row', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create(['letter' => 'Z']);

    $response = $this->get("/admin/rows/{$row->letter}/export-qr-codes");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when exporting QR codes for a row', function () {
    $row = Row::factory()->create();

    $response = $this->get("/admin/rows/{$row->letter}/export-qr-codes");

    $response->assertRedirect(route('login'));
});

test('exporting QR codes for a non-existent row returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/rows/ZZ/export-qr-codes');

    $response->assertNotFound();
});
