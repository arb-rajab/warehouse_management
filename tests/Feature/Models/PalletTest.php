<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Row;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('a pallet belongs to its product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    expect($pallet->product->id)->toBe($product->id);
    expect($pallet->product->id)->not->toBe($otherProduct->id);
});

test('a pallet can be created with its fillable remaining_boxes attribute', function () {
    $pallet = Pallet::factory()->create(['remaining_boxes' => 6]);

    expect($pallet->fresh()->remaining_boxes)->toBe(6);
});

test('a pallet belongs to its cell', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id]);

    expect($pallet->cell->id)->toBe($cell->id);
    expect($pallet->cell->id)->not->toBe($otherCell->id);
});

test('the expiration_date attribute is cast to a date', function () {
    $pallet = Pallet::factory()->create(['expiration_date' => '2027-01-15']);

    expect($pallet->fresh()->expiration_date)->toBeInstanceOf(CarbonImmutable::class);
    expect($pallet->fresh()->expiration_date->toDateString())->toBe('2027-01-15');
});

test('a pallet can be created with a null expiration_date', function () {
    $pallet = Pallet::factory()->create(['expiration_date' => null]);

    expect($pallet->fresh()->expiration_date)->toBeNull();
});

test('the factory default expiration_date has no time component', function () {
    // fake()->dateTimeBetween() returns a random time of day; PalletFactory
    // must format it down to a bare date, or the raw stored value carries
    // that random time — which SQLite (unlike MySQL's DATE column) keeps
    // verbatim, breaking `expiration_date <= $until` boundary comparisons
    // like BuildsDashboardStats::expiringWindowCounts()'s.
    $pallet = Pallet::factory()->create();

    $raw = DB::table('pallets')->where('id', $pallet->id)->value('expiration_date');

    expect($raw)->toEndWith(' 00:00:00');
});

test('the state attribute reads the state of the pallets current cell', function () {
    $fullPallet = Pallet::factory()->create();
    $openedPallet = Pallet::factory()->opened()->create();

    expect($fullPallet->state)->toBe(CellState::Full);
    expect($openedPallet->state)->toBe(CellState::Opened);
});

test('isStaleAfter is false for a freshly stored pallet given any caller-chosen day count', function () {
    $pallet = Pallet::factory()->create();

    expect($pallet->isStaleAfter(3))->toBeFalse();
});

test('isStaleAfter is true once a pallet has been stored longer than the given day count', function () {
    $pallet = Pallet::factory()->stale()->create();

    expect($pallet->fresh()->isStaleAfter(3))->toBeTrue();
});

test('isStaleAfter is true exactly at the given day count boundary', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $days = 5;
    $pallet = backdate(
        Pallet::factory()->create(),
        now()->subDays($days)->toDateTimeString(),
    );

    expect($pallet->fresh()->isStaleAfter($days))->toBeTrue();

    Carbon::setTestNow();
});

test('isStaleAfter is false when the pallet is younger than the given day count', function () {
    $pallet = backdate(
        Pallet::factory()->create(),
        now()->subDays(2)->toDateTimeString(),
    );

    expect($pallet->fresh()->isStaleAfter(5))->toBeFalse();
});

test('toMapSummaryArray carries both raw store name columns, whatever the locale', function () {
    // Nothing is resolved here: `lib/productName.ts` picks the label from the
    // active locale client-side, so the payload is locale-independent.
    $product = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    // Noise: another pallet's product must not supply either name.
    Pallet::factory()->create(['product_id' => Product::factory()->create(['name' => 'Gadgets', 'ar_name' => 'أدوات'])->id]);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    app()->setLocale('ar');

    expect($pallet->toMapSummaryArray())
        ->product_name->toBe('Widgets')
        ->product_ar_name->toBe('ودجات');
});

test('toMapSummaryArray carries an empty product_ar_name for a product the store never translated', function () {
    // NOT NULL upstream, so an untranslated product ships `''` rather than
    // null — the frontend resolver falls back on exactly that.
    $product = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => '']);
    $pallet = Pallet::factory()->create(['product_id' => $product->id]);

    app()->setLocale('ar');

    expect($pallet->toMapSummaryArray())
        ->product_name->toBe('Widgets')
        ->product_ar_name->toBe('');
});

test('toMapSummaryArray describes the pallet by its product, expiration date, and added_at', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $product = Product::factory()->imageUrl('https://example.com/widgets.png')->create([
        'name' => 'Widgets',
        'ar_name' => 'ودجات',
    ]);
    $pallet = Pallet::factory()->create([
        'product_id' => $product->id,
        'expiration_date' => '2026-09-15',
    ]);

    expect($pallet->toMapSummaryArray())->toBe([
        'product_id' => $product->id,
        'product_name' => 'Widgets',
        'product_ar_name' => 'ودجات',
        'product_active' => true,
        'product_image_url' => 'https://example.com/widgets.png',
        'expiration_date' => '2026-09-15',
        'added_at' => '2026-08-01T10:00:00+00:00',
        'cell_entered_at' => null,
    ]);

    Carbon::setTestNow();
});

test('toMapSummaryArray carries a null expiration_date for a pallet with none, without throwing', function () {
    $pallet = Pallet::factory()->create(['expiration_date' => null]);

    expect($pallet->toMapSummaryArray())->expiration_date->toBeNull();
});

test('toMapSummaryArray carries product_active mirroring the product\'s published flag', function () {
    $activeProduct = Product::factory()->imageUrl(null)->create(['published' => true]);
    $inactiveProduct = Product::factory()->imageUrl(null)->create(['published' => false]);

    $activePallet = Pallet::factory()->create(['product_id' => $activeProduct->id]);
    $inactivePallet = Pallet::factory()->create(['product_id' => $inactiveProduct->id]);

    expect($activePallet->toMapSummaryArray())->product_active->toBeTrue()
        ->and($inactivePallet->toMapSummaryArray())->product_active->toBeFalse();
});

test('cellEnteredLog resolves the most recent stored or transferred_in log for the pallet', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    $pallet = Pallet::factory()->create();
    $storedLog = CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $pallet->cell_id,
        'action' => CellLogAction::Stored,
    ]);

    // Noise: an unrelated pallet's stored log, and this pallet's own opened log
    // (not a "stored"/"transferred_in" action, so must not win).
    CellStatusLog::factory()->create(['action' => CellLogAction::Stored]);
    CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $pallet->cell_id,
        'action' => CellLogAction::Opened,
        'created_at' => now()->addHour(),
    ]);

    expect($pallet->fresh()->cellEnteredLog->id)->toBe($storedLog->id);

    Carbon::setTestNow();
});

test('cellEnteredLog moves to the newest transferred_in log once the pallet is transferred', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');
    $pallet = Pallet::factory()->create();
    CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $pallet->cell_id,
        'action' => CellLogAction::Stored,
    ]);

    Carbon::setTestNow('2026-08-05 10:00:00');
    $destinationCell = Cell::factory()->create();
    $pallet->update(['cell_id' => $destinationCell->id]);
    $transferredInLog = CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $destinationCell->id,
        'action' => CellLogAction::TransferredIn,
    ]);

    expect($pallet->fresh()->cellEnteredLog->id)->toBe($transferredInLog->id);
    expect($pallet->fresh()->cell_entered_at->toIso8601String())->toBe('2026-08-05T10:00:00+00:00');

    Carbon::setTestNow();
});

test('matchingHighlightFilters matches expires_within_days from today through the window only, excluding an already-expired and a later pallet', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    $expiringSoon = Pallet::factory()->create(['expiration_date' => '2026-08-20']);
    $expiresToday = Pallet::factory()->create(['expiration_date' => '2026-08-15']);
    // Noise: already past due, and well beyond the window.
    Pallet::factory()->create(['expiration_date' => '2026-08-01']);
    Pallet::factory()->create(['expiration_date' => '2026-12-01']);

    $request = Request::create('/', 'GET', ['expires_within_days' => 7]);

    expect(Pallet::query()->matchingHighlightFilters($request)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$expiringSoon->id, $expiresToday->id])->sort()->values()->all());

    Carbon::setTestNow();
});

test('matchingHighlightFilters filters by the pallet cell state and inactive flag, excluding a pallet in another cell', function () {
    $opened = Pallet::factory()->opened()->create();
    // Noise: a full pallet.
    Pallet::factory()->create();
    $inactive = Pallet::factory()->create();
    $inactive->cell->update(['is_active' => false]);

    expect(Pallet::query()->matchingHighlightFilters(Request::create('/', 'GET', ['state' => 'opened']))->pluck('id')->all())
        ->toBe([$opened->id])
        ->and(Pallet::query()->matchingHighlightFilters(Request::create('/', 'GET', ['is_active' => '0']))->pluck('id')->all())
        ->toBe([$inactive->id]);
});

test('matchingHighlightFilters filters by product, excluding another product\'s pallet', function () {
    $matching = Pallet::factory()->create();
    // Noise: another product's pallet.
    Pallet::factory()->create();

    $request = Request::create('/', 'GET', ['product_id' => [$matching->product_id]]);

    expect(Pallet::query()->matchingHighlightFilters($request)->pluck('id')->all())->toBe([$matching->id]);
});

test('matchingOccupancyFilters matches expires_within_days as on-or-before the window end, including an already-expired pallet but excluding a later one', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    $expiringSoon = Pallet::factory()->create(['expiration_date' => '2026-08-20']);
    $alreadyExpired = Pallet::factory()->create(['expiration_date' => '2026-08-01']);
    // Noise: well beyond the window, and no expiration date at all.
    Pallet::factory()->create(['expiration_date' => '2026-12-01']);
    Pallet::factory()->create(['expiration_date' => null]);

    $request = Request::create('/', 'GET', ['expires_within_days' => 7]);

    expect(Pallet::query()->matchingOccupancyFilters($request)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$expiringSoon->id, $alreadyExpired->id])->sort()->values()->all());

    Carbon::setTestNow();
});

test('matchingOccupancyFilters filters by expired, excluding a pallet not yet expired', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');
    $expired = Pallet::factory()->create(['expiration_date' => '2026-08-01']);
    // Noise: expires later.
    Pallet::factory()->create(['expiration_date' => '2026-09-01']);

    $request = Request::create('/', 'GET', ['expired' => 'true']);

    expect(Pallet::query()->matchingOccupancyFilters($request)->pluck('id')->all())->toBe([$expired->id]);

    Carbon::setTestNow();
});

test('matchingOccupancyFilters filters by the requested cell state, excluding a pallet in a cell of another state', function () {
    $full = Pallet::factory()->create();
    // Noise: an opened pallet.
    Pallet::factory()->opened()->create();

    $request = Request::create('/', 'GET', ['state' => 'full']);

    expect(Pallet::query()->matchingOccupancyFilters($request)->pluck('id')->all())->toBe([$full->id]);
});

test('matchingOccupancyFilters pins the given cell state on top of the request filters, excluding a pallet in another state', function () {
    $opened = Pallet::factory()->opened()->create();
    // Noise: a full pallet.
    Pallet::factory()->create();

    $request = Request::create('/', 'GET');

    expect(Pallet::query()->matchingOccupancyFilters($request, CellState::Opened)->pluck('id')->all())->toBe([$opened->id])
        ->and(Pallet::query()->matchingOccupancyFilters($request)->count())->toBe(2);
});

test('forWarehouses narrows pallets to the cells of the given warehouses and is a no-op for null', function () {
    $north = Warehouse::factory()->create();
    $northRow = Row::factory()->for($north)->create(['cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $inNorth = Pallet::factory()->create(['cell_id' => $northRow->cells()->value('id')]);
    $noise = Pallet::factory()->create(['cell_id' => $otherRow->cells()->value('id')]);

    expect(Pallet::query()->forWarehouses([$north->id])->pluck('id')->all())->toBe([$inNorth->id])
        ->and(Pallet::query()->forWarehouses(null)->pluck('id'))->toContain($noise->id);
});

test('a pallet belongs to the receipt it arrived on, if any', function () {
    $receipt = Receipt::factory()->finalized()->create();
    $received = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);
    $plain = Pallet::factory()->create();
    Receipt::factory()->finalized()->create(); // noise

    expect($received->receipt->id)->toBe($receipt->id)
        ->and($plain->receipt_id)->toBeNull()
        ->and($plain->fresh()->receipt)->toBeNull();
});

test('the unplaced factory state builds a pallet with no cell and a finalized receipt of its own, without creating a cell', function () {
    $pallet = Pallet::factory()->unplaced()->create();

    expect($pallet->cell_id)->toBeNull()
        ->and($pallet->receipt->isFinalized())->toBeTrue()
        ->and($pallet->receipt->warehouse_id)->toBe(Warehouse::firstOrCreateDefault()->id)
        ->and(Cell::query()->count())->toBe(0);
});

test('the unplaced factory state takes the receipt it is given', function () {
    $receipt = Receipt::factory()->finalized()->for(Warehouse::factory()->create())->create();

    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => $receipt->id]);

    expect($pallet->receipt_id)->toBe($receipt->id)
        ->and($pallet->warehouse->id)->toBe($receipt->warehouse_id);
});

test('the warehouse of a placed pallet is its cells row warehouse', function () {
    $north = Warehouse::factory()->create();
    $pallet = placedPalletIn($north);
    placedPalletIn(Warehouse::factory()->create()); // noise

    expect($pallet->warehouse->id)->toBe($north->id);
});

test('the warehouse of an unplaced pallet is its receipts warehouse', function () {
    $north = Warehouse::factory()->create();
    $pallet = unplacedPalletIn($north);
    Pallet::factory()->unplaced()->create(); // noise, Default warehouse

    expect($pallet->warehouse->id)->toBe($north->id);
});

test('a received pallet that was placed in another warehouse is in the warehouse of its cell, not of its receipt', function () {
    $receivedInto = Warehouse::factory()->create();
    $movedTo = Warehouse::factory()->create();
    $row = Row::factory()->for($movedTo)->create(['cells_count' => 1, 'flats_count' => 1]);
    $pallet = unplacedPalletIn($receivedInto);

    $pallet->update(['cell_id' => $row->cells()->value('id')]);

    expect($pallet->fresh()->warehouse->id)->toBe($movedTo->id)
        ->and(Pallet::query()->forWarehouses([$movedTo->id])->pluck('id')->all())->toBe([$pallet->id])
        ->and(Pallet::query()->forWarehouses([$receivedInto->id])->count())->toBe(0);
});

test('the warehouse of a pallet with neither a cell nor a receipt is null', function () {
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => null]);

    expect($pallet->warehouse)->toBeNull();
});

test('warehouseLoaded says whether the relations the warehouse reads are loaded, for a placed pallet', function () {
    $pallet = placedPalletIn(Warehouse::factory()->create());
    $bare = Pallet::query()->findOrFail($pallet->id);
    $partial = Pallet::query()->with('cell.row')->findOrFail($pallet->id);
    $loaded = Pallet::query()->with('cell.row.warehouse')->findOrFail($pallet->id);

    expect($bare->warehouseLoaded())->toBeFalse()
        ->and($partial->warehouseLoaded())->toBeFalse()
        ->and($loaded->warehouseLoaded())->toBeTrue();
});

test('warehouseLoaded says whether the receipts warehouse is loaded, for an unplaced pallet', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $bare = Pallet::query()->findOrFail($pallet->id);
    $partial = Pallet::query()->with('receipt:id,warehouse_id')->findOrFail($pallet->id);
    $loaded = Pallet::query()->with(Pallet::WITH_RECEIPT_WAREHOUSE)->findOrFail($pallet->id);

    expect($bare->warehouseLoaded())->toBeFalse()
        ->and($partial->warehouseLoaded())->toBeFalse()
        ->and($loaded->warehouseLoaded())->toBeTrue()
        ->and($loaded->warehouse->id)->toBe($pallet->receipt->warehouse_id);
});

test('warehouseLoaded is true for a pallet with no receipt once the receipt relation is loaded as null', function () {
    $pallet = Pallet::factory()->unplaced()->create(['receipt_id' => null]);

    expect(Pallet::query()->findOrFail($pallet->id)->warehouseLoaded())->toBeFalse()
        ->and(Pallet::query()->with('receipt')->findOrFail($pallet->id)->warehouseLoaded())->toBeTrue();
});

test('forWarehouses matches a placed pallet by its row and an unplaced one by its receipts warehouse', function () {
    $north = Warehouse::factory()->create();
    $south = Warehouse::factory()->create();
    $placedNorth = placedPalletIn($north);
    $placedSouth = placedPalletIn($south); // noise
    $unplacedNorth = unplacedPalletIn($north);
    $unplacedSouth = unplacedPalletIn($south); // noise

    expect(Pallet::query()->forWarehouses([$north->id])->pluck('id')->sort()->values()->all())
        ->toBe([$placedNorth->id, $unplacedNorth->id])
        ->and(Pallet::query()->forWarehouses([$south->id])->pluck('id')->sort()->values()->all())
        ->toBe([$placedSouth->id, $unplacedSouth->id])
        ->and(Pallet::query()->forWarehouses(null)->count())->toBe(4)
        ->and(Pallet::query()->forWarehouses([])->count())->toBe(0);
});

test('a placed pallet that arrived on a receipt of another warehouse is not matched through that receipt', function () {
    $receivedInto = Warehouse::factory()->create();
    $placedIn = Warehouse::factory()->create();
    $pallet = placedPalletIn($placedIn, ['receipt_id' => Receipt::factory()->finalized()->for($receivedInto)->create()->id]);

    expect(Pallet::query()->forWarehouses([$receivedInto->id])->count())->toBe(0)
        ->and(Pallet::query()->forWarehouses([$placedIn->id])->pluck('id')->all())->toBe([$pallet->id]);
});

test('the placed and unplaced scopes split pallets by whether they have a cell', function () {
    $placed = Pallet::factory()->create();
    $unplaced = Pallet::factory()->unplaced()->create();

    expect(Pallet::query()->placed()->pluck('id')->all())->toBe([$placed->id])
        ->and(Pallet::query()->unplaced()->pluck('id')->all())->toBe([$unplaced->id]);
});

test('the placed and unplaced scopes qualify cell_id, so they compose with a join', function () {
    $placed = Pallet::factory()->create();
    Pallet::factory()->unplaced()->create(); // noise

    $ids = Pallet::query()
        ->leftJoin('cells', 'cells.id', '=', 'pallets.cell_id')
        ->placed()
        ->pluck('pallets.id')
        ->all();

    expect($ids)->toBe([$placed->id]);
});

test('the state of an unplaced pallet is null and is read without touching the cell relation', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $fresh = Pallet::query()->findOrFail($pallet->id);

    expect($fresh->state)->toBeNull()
        ->and($fresh->relationLoaded('cell'))->toBeFalse();
});

test('the etag works without a cell and changes when the pallet is placed', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $unplacedEtag = Pallet::query()->findOrFail($pallet->id)->etag();

    expect($unplacedEtag)->toBeString()->toHaveLength(32)
        ->and(Pallet::query()->findOrFail($pallet->id)->etag())->toBe($unplacedEtag);

    $pallet->update(['cell_id' => $row->cells()->value('id')]);

    expect(Pallet::query()->with('cell')->findOrFail($pallet->id)->etag())->not->toBe($unplacedEtag);
});

test('the etag of a pallet without a note is the one it had before notes existed', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $fresh = Pallet::query()->findOrFail($pallet->id);

    expect($fresh->etag())->toBe(md5(implode('|', [
        $fresh->id,
        $fresh->product_id,
        $fresh->cell_id,
        $fresh->expiration_date?->toDateString(),
        $fresh->remaining_boxes,
        null,
        $fresh->updated_at?->getTimestamp(),
    ])));
});

test('the etag changes with the note, even within the same second', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $fresh = fn () => Pallet::query()->findOrFail($pallet->id);
    $timestamp = $fresh()->updated_at;

    $without = $fresh()->etag();
    Pallet::query()->whereKey($pallet->id)->update(['note' => 'Brand X', 'updated_at' => $timestamp]);
    $withX = $fresh()->etag();
    Pallet::query()->whereKey($pallet->id)->update(['note' => 'Brand Y', 'updated_at' => $timestamp]);

    expect($withX)->not->toBe($without)
        ->and($fresh()->etag())->not->toBe($withX);
});

test('note is fillable and nullable', function () {
    $pallet = Pallet::factory()->create(['note' => 'Olive oil 5L']);

    expect($pallet->fresh()->note)->toBe('Olive oil 5L');

    $pallet->update(['note' => null]);

    expect($pallet->fresh()->note)->toBeNull();
});

test('the etag of an unplaced pallet differs from another unplaced pallets', function () {
    $first = Pallet::factory()->unplaced()->create();
    $second = Pallet::factory()->unplaced()->create();

    expect($first->etag())->not->toBe($second->etag());
});

test('toListingSummaryArray carries a null state for an unplaced pallet', function () {
    $pallet = Pallet::factory()->unplaced()->create(['expiration_date' => '2027-01-15', 'remaining_boxes' => 8]);

    expect(Pallet::query()->findOrFail($pallet->id)->toListingSummaryArray())->toBe([
        'id' => $pallet->id,
        'state' => null,
        'expiration_date' => '2027-01-15',
        'added_at' => $pallet->created_at->toIso8601String(),
        'remaining_boxes' => 8,
    ]);
});

test('the cell entered log of an unplaced pallet is null and of a placed one is its arrival', function () {
    $unplaced = Pallet::factory()->unplaced()->create();
    $placed = Pallet::factory()->create();
    $log = CellStatusLog::factory()->create([
        'pallet_id' => $placed->id,
        'cell_id' => $placed->cell_id,
        'action' => CellLogAction::Stored,
    ]);

    expect(Pallet::query()->findOrFail($unplaced->id)->cellEnteredLog)->toBeNull()
        ->and(Pallet::query()->findOrFail($unplaced->id)->cell_entered_at)->toBeNull()
        ->and(Pallet::query()->findOrFail($placed->id)->cellEnteredLog->id)->toBe($log->id);
});

test('listedForAdmin includes unplaced pallets, with their receipt warehouse loaded', function () {
    $placed = Pallet::factory()->create(['expiration_date' => '2026-09-01']);
    $unplaced = Pallet::factory()->unplaced()->create(['expiration_date' => '2026-08-01']);

    $listed = Pallet::query()->listedForAdmin()->get();

    expect($listed->pluck('id')->all())->toBe([$unplaced->id, $placed->id])
        ->and($listed->first()->warehouseLoaded())->toBeTrue()
        ->and($listed->first()->receipt->warehouse->id)->toBe($unplaced->receipt->warehouse_id);
});

test('placing an unplaced pallet does not change its receipt link', function () {
    $pallet = Pallet::factory()->unplaced()->create();
    $receiptId = $pallet->receipt_id;
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);

    $pallet->update(['cell_id' => $row->cells()->value('id')]);

    expect($pallet->fresh()->receipt_id)->toBe($receiptId);
});
