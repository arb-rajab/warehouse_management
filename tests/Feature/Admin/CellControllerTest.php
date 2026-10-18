<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

test('an authenticated user can export a QR code image for a single cell', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $response->assertHeader('content-type', 'image/svg+xml');

    $svg = $response->getContent();
    expect($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0"')
        ->and($svg)->toContain('<path fill-rule="evenodd"')
        ->and($svg)->not->toContain('<image');
    expect($svg)->toContain(Cell::slotLabel('Z', $cell->cell_number, $cell->flat_number));

    expect($svg)->toContain('font-size="121" font-weight="bold"');
});

test('a single-cell QR export draws a larger downward arrow below the label for the lowest level of a multi-level row', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 1)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="648,400 551,303 609,303 609,206 687,206 687,303 745,303" fill="#111111"/>');
});

test('a single-cell QR export draws a larger upward arrow below the label for the highest level of a multi-level row', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 3)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="648,206 551,303 609,303 609,400 687,400 687,303 745,303" fill="#111111"/>');
});

test('a single-cell QR export draws a horizontal arrow below the label for a middle level', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 2)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="745,303 648,206 648,264 551,264 551,342 648,342 648,400" fill="#111111"/>');
});

test('a single-cell QR export draws a horizontal arrow below the label when the row has only one level', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="745,303 648,206 648,264 551,264 551,342 648,342 648,400" fill="#111111"/>');
});

test('a single-cell QR export uses the configured QR code size instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="400"')
        ->and($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0" width="187" height="187"');
});

test('a single-cell QR export keeps the full slot label visible for large cell/flat numbers', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z']);
    $cell = Cell::factory()->for($row)->create(['cell_number' => 123, 'flat_number' => 456]);

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('Z123·456')
        ->and($svg)->not->toContain('…');
});

test('a single-cell QR export keeps QR and label side by side when width and height are equal', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 280, 'qr_code_height' => 280]);
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain(Cell::slotLabel('Z', $cell->cell_number, $cell->flat_number))
        ->and($svg)->toContain('<g transform="translate(14,14)"><rect x="0" y="0" width="131" height="131"')
        ->and($svg)->toContain('height="159"')
        ->and($svg)->toContain('font-weight="bold"');
});

test('a horizontal cell QR keeps the full bold slot and arrow inside the canvas', function (int $width, int $height, string $locale) {
    actingAsAdmin();
    app()->setLocale($locale);
    Setting::factory()->create(['qr_code_width' => $width, 'qr_code_height' => $height]);
    $row = Row::factory()->create(['letter' => 'ZZ', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = Cell::factory()->for($row)->create(['cell_number' => 123, 'flat_number' => 456]);

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code")->assertOk();
    $svg = simplexml_load_string($response->getContent());
    $svg->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
    $text = $svg->xpath('//s:text')[0];
    $qr = $svg->xpath('//s:g/s:rect')[0];
    $arrow = $svg->xpath('//s:polygon')[0];
    preg_match('/translate\((\d+),(\d+)\)/', (string) $svg->xpath('//s:g')[0]['transform'], $translation);
    $qrRight = (int) $translation[1] + (int) $qr['width'];
    $centerY = (int) $translation[2] + (int) $qr['height'] / 2;
    $fontSize = (int) $text['font-size'];

    expect((string) $text)->toBe('ZZ123·456')
        ->and((string) $text['font-weight'])->toBe('bold')
        ->and((string) $text['direction'])->toBe('ltr')
        ->and((int) $svg['width'])->toBe($width)
        ->and((int) $svg['height'])->toBeLessThanOrEqual($height)
        ->and((int) $text['x'] - mb_strlen((string) $text) * $fontSize * 0.75 / 2)->toBeGreaterThan($qrRight)
        ->and((int) $text['y'])->toBeLessThan($centerY);

    $arrowXs = [];
    $arrowYs = [];

    foreach (explode(' ', (string) $arrow['points']) as $point) {
        [$x, $y] = array_map('intval', explode(',', $point));
        $arrowXs[] = $x;
        $arrowYs[] = $y;
        expect($x)->toBeGreaterThan($qrRight)->toBeLessThan($width)
            ->and($y)->toBeGreaterThanOrEqual(0)->toBeLessThan((int) $svg['height']);
    }

    expect(min($arrowYs))->toBeGreaterThan((int) $text['y'] + $fontSize * 0.2)
        ->and(abs((min($arrowXs) + max($arrowXs)) / 2 - (int) $text['x']))->toBeLessThanOrEqual(1)
        ->and(abs(((int) $text['y'] - round($fontSize * 0.8) + max($arrowYs)) / 2 - $centerY))->toBeLessThanOrEqual(1);
})->with([
    [850, 1000, 'en'],
    [280, 280, 'en'],
    [100, 100, 'en'],
    [1000, 100, 'en'],
    [100, 1000, 'en'],
    [850, 1000, 'ar'],
]);

test('a mobile app user cannot export a single cells QR code', function () {
    actingAsMobilePanelUser();
    $row = Row::factory()->create();
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when exporting a single cells QR code', function () {
    $row = Row::factory()->create();
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertRedirect(route('login'));
});

test('exporting a QR code for a non-existent cell returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cells/999999/export-qr-code');

    $response->assertNotFound();
});

test('an authenticated admin can deactivate a cell regardless of its occupancy', function () {
    $admin = actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $cell->id]);
    $cell->update(['state' => CellState::Full]);

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active", ['note' => 'Sensor malfunction']);

    $response->assertRedirect();
    expect($cell->fresh()->is_active)->toBeFalse();
    expect($cell->fresh()->state)->toBe(CellState::Full);

    $log = CellStatusLog::query()->latest('id')->first();
    expect($log->cell_id)->toBe($cell->id);
    expect($log->action)->toBe(CellLogAction::Deactivated);
    expect($log->from_state)->toBe(CellState::Full);
    expect($log->to_state)->toBe(CellState::Full);
    expect($log->user_id)->toBe($admin->id);
    expect($log->note)->toBe('Sensor malfunction');
    expect($log->product_id)->toBe($product->id);
    expect($log->pallet_id)->toBe($pallet->id);
});

test('an authenticated admin can reactivate a cell, and its occupancy state is never touched by either toggle', function () {
    $admin = actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $product = Product::factory()->create();
    $pallet = Pallet::factory()->create(['product_id' => $product->id, 'cell_id' => $cell->id]);
    $cell->update(['state' => CellState::Opened]);

    CellStatusLog::create([
        'cell_id' => $cell->id,
        'action' => CellLogAction::Opened,
        'from_state' => CellState::Full,
        'to_state' => CellState::Opened,
        'product_id' => $product->id,
        'pallet_id' => $pallet->id,
        'user_id' => $admin->id,
    ]);

    $this->post("/admin/cells/{$cell->id}/toggle-active")->assertRedirect();
    expect($cell->fresh()->is_active)->toBeFalse();
    expect($cell->fresh()->state)->toBe(CellState::Opened);

    $this->post("/admin/cells/{$cell->id}/toggle-active")->assertRedirect();

    expect($cell->fresh()->is_active)->toBeTrue();
    expect($cell->fresh()->state)->toBe(CellState::Opened);

    $log = CellStatusLog::query()->latest('id')->first();
    expect($log->action)->toBe(CellLogAction::Reactivated);
    expect($log->from_state)->toBe(CellState::Opened);
    expect($log->to_state)->toBe(CellState::Opened);
});

test('a mobile app user cannot toggle a cells active status', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    actingAsMobilePanelUser();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertForbidden();
    expect($cell->fresh()->is_active)->toBeTrue();
});

test('an unauthenticated caller cannot toggle a cells active status', function () {
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertRedirect(route('login'));
    expect($cell->fresh()->is_active)->toBeTrue();
});

test('toggling the active status of a non-existent cell returns a 404', function () {
    actingAsAdmin();

    $response = $this->post('/admin/cells/999999/toggle-active');

    $response->assertNotFound();
});

test('toggling a cells active status redirects back to that cells row page', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $row = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->post("/admin/cells/{$cell->id}/toggle-active");

    $response->assertRedirect(route('admin.rows.show', 'B'));
});

test('the retired cell map page is gone', function () {
    actingAsAdmin();

    expect(Route::has('admin.cells.index'))->toBeFalse();
    $this->get('/admin/cells')->assertNotFound();
});
