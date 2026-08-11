<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell log with every property the table renders', function () {
    actingAsAdmin();
    $mover = User::factory()->mobileUser()->create(['name' => 'Bob Mover']);

    $fromRow = Row::factory()->create(['letter' => 'A', 'cells_count' => 2, 'flats_count' => 2]);
    $toRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 2, 'flats_count' => 2]);
    $fromCell = $fromRow->cells()->where('cell_number', 1)->where('flat_number', 2)->first();
    $toCell = $toRow->cells()->where('cell_number', 2)->where('flat_number', 1)->first();

    $product = Product::factory()->create([
        'name' => 'Widgets',
        'image_url' => 'https://cdn.example.com/widgets.png',
    ]);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'cell_id' => $toCell->id,
        'expiration_date' => '2026-09-01',
    ]);

    $log = CellStatusLog::factory()->create([
        'cell_id' => $fromCell->id,
        'related_cell_id' => $toCell->id,
        'action' => CellLogAction::TransferredOut,
        'from_state' => CellState::Full,
        'to_state' => CellState::Empty,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'user_id' => $mover->id,
        'note' => 'Consolidating stock.',
    ]);

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellStatusLogs/Index')
            ->has('logs.data', 1)
            ->has('logs.data.0', fn (Assert $logProp) => $logProp
                ->where('id', $log->id)
                ->where('action', 'transferred_out')
                ->where('from_state', 'full')
                ->where('to_state', 'empty')
                ->where('note', 'Consolidating stock.')
                ->where('created_at', $log->created_at->toIso8601String())
                ->has('cell', fn (Assert $cell) => $cell
                    ->where('row_letter', 'A')
                    ->where('cell_number', 1)
                    ->where('flat_number', 2)
                )
                ->has('related_cell', fn (Assert $relatedCell) => $relatedCell
                    ->where('row_letter', 'B')
                    ->where('cell_number', 2)
                    ->where('flat_number', 1)
                )
                ->has('product', fn (Assert $productProp) => $productProp
                    ->where('id', $product->id)
                    ->where('name', 'Widgets')
                    ->where('image_url', 'https://cdn.example.com/widgets.png')
                )
                ->has('pallet', fn (Assert $palletProp) => $palletProp
                    ->where('id', $pallet->id)
                    ->where('expiration_date', '2026-09-01')
                )
                ->has('user', fn (Assert $userProp) => $userProp
                    ->where('id', $mover->id)
                    ->where('name', 'Bob Mover')
                )
            )
            ->has('filterOptions.rows', 2)
            ->where('filterOptions.maxColumnNumber', 2)
            ->has('filterOptions.actions', 5)
    );
});

test('the cell log paginates instead of returning everything at once', function () {
    actingAsAdmin();

    CellStatusLog::factory()->count(30)->create();

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellStatusLogs/Index')
            ->has('logs.data', 25)
            ->where('logs.meta.total', 30)
    );
});

test('a mobile app user cannot view the cell log', function () {
    $mobileUser = User::factory()->mobileUser()->create();

    $response = $this->actingAs($mobileUser)->get('/admin/cell-logs');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login', function () {
    $response = $this->get('/admin/cell-logs');

    $response->assertRedirect(route('login'));
});

test('the cell log can be filtered by product, excluding entries for other products', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $product->id]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id]);

    $response = $this->get("/admin/cell-logs?product_id={$product->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log shows a pallet id with a null expiration date once the pallet has been emptied and deleted', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;

    $log = CellStatusLog::factory()->create([
        'action' => CellLogAction::Emptied,
        'pallet_id' => $palletId,
    ]);

    $pallet->delete();

    $response = $this->get('/admin/cell-logs');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data.0', fn (Assert $logProp) => $logProp
            ->where('id', $log->id)
            ->has('pallet', fn (Assert $palletProp) => $palletProp
                ->where('id', $palletId)
                ->where('expiration_date', null)
            )
            ->etc()
        )
    );
});

test('the cell log can be filtered by pallet, excluding entries for other pallets, even after the pallet is deleted', function () {
    actingAsAdmin();
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;
    $otherPallet = Pallet::factory()->create();

    $matching = CellStatusLog::factory()->create(['pallet_id' => $palletId]);
    CellStatusLog::factory()->create(['pallet_id' => $otherPallet->id]);

    $pallet->delete();

    $response = $this->get("/admin/cell-logs?pallet_id={$palletId}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by row, excluding entries for other rows', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $otherCell = $otherRow->cells()->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $cell->id]);
    CellStatusLog::factory()->create(['cell_id' => $otherCell->id]);

    $response = $this->get("/admin/cell-logs?row_id={$row->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by column number, excluding entries for other columns', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 2, 'flats_count' => 1]);
    $columnOneCell = $row->cells()->where('cell_number', 1)->first();
    $columnTwoCell = $row->cells()->where('cell_number', 2)->first();

    $matching = CellStatusLog::factory()->create(['cell_id' => $columnOneCell->id]);
    CellStatusLog::factory()->create(['cell_id' => $columnTwoCell->id]);

    $response = $this->get('/admin/cell-logs?column_number=1');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by who did it, excluding entries by other users', function () {
    actingAsAdmin();
    $mover = User::factory()->create();
    $otherMover = User::factory()->create();

    $matching = CellStatusLog::factory()->create(['user_id' => $mover->id]);
    CellStatusLog::factory()->create(['user_id' => $otherMover->id]);

    $response = $this->get("/admin/cell-logs?user_id={$mover->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by status change, excluding entries for other actions', function () {
    actingAsAdmin();

    $matching = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
    CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);

    $response = $this->get('/admin/cell-logs?action=opened');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('the cell log can be filtered by a date range, excluding entries outside it', function () {
    actingAsAdmin();

    $matching = CellStatusLog::factory()->create();
    $matching->forceFill(['created_at' => '2026-06-15'])->save();

    $outOfRange = CellStatusLog::factory()->create();
    $outOfRange->forceFill(['created_at' => '2026-01-01'])->save();

    $response = $this->get('/admin/cell-logs?date_from=2026-06-01&date_to=2026-06-30');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});
