<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use Carbon\CarbonImmutable;
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

test('the factory default expiration_date has no time component', function () {
    // fake()->dateTimeBetween() returns a random time of day; PalletFactory
    // must format it down to a bare date, or the raw stored value carries
    // that random time — which SQLite (unlike MySQL's DATE column) keeps
    // verbatim, breaking `expiration_date <= $until` boundary comparisons
    // like BuildsDashboardStats::expiringWindow()'s.
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
        'product_image_url' => 'https://example.com/widgets.png',
        'expiration_date' => '2026-09-15',
        'added_at' => '2026-08-01T10:00:00+00:00',
        'cell_entered_at' => null,
    ]);

    Carbon::setTestNow();
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
