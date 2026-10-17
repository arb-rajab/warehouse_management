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
    // A real QR is spliced in as inline SVG markup rather than a nested
    // `<image>` reference, which dompdf silently skips (see
    // BuildsQrLabels::qrSvgInnerMarkup()) — regression guard for the QR
    // failing to render rather than just the surrounding text.
    expect($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0"')
        ->and($svg)->toContain('<path fill-rule="evenodd"')
        ->and($svg)->not->toContain('<image');
    expect($svg)->toContain(Cell::slotLabel('Z', $cell->cell_number, $cell->flat_number));

    // With no description line to share the label with, the slot label grows
    // to fill the freed vertical space (BuildsQrLabels::qrLabelImage()'s
    // expandPrimaryText) — well past the 18px used everywhere else (e.g. the
    // product QR export's primary line). Neither this single-cell SVG export
    // nor the row-wide PDF sheet (see RowControllerTest, which now embeds
    // this exact same SVG verbatim on each cell's page — see
    // BuildsCellQrLabels::cellQrLabels()) carries a spelled-out description
    // line at all any more.
    expect($svg)->toContain('font-size="64"');
});

test('a single-cell QR export draws a same-height downward arrow beside the label for the lowest level of a multi-level row', function () {
    // The row has no Setting, so Setting::current() defaults to
    // qr_code_width=850 (Setting::DEFAULT_QR_CODE_WIDTH), and the label is
    // short enough to stay on one line at the max 64px font — both pin the
    // arrow's box to these exact points (see
    // BuildsQrLabels::qrLabelImage()'s $primaryArrow reservation math).
    // A downward arrow's shaft points away from its head, which sits at the
    // bottom (708,906 is the tip; 587..830 the shaft/head span above it).
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 1)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="708,906 587,874 659,874 659,842 757,842 757,874 830,874" fill="#111111"/>');
});

test('a single-cell QR export draws a same-height upward arrow beside the label for the highest level of a multi-level row', function () {
    // Same geometry as the downward-arrow test above, mirrored vertically —
    // the tip (708,842) sits at the top instead of the bottom.
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 3)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="708,842 587,874 659,874 659,906 757,906 757,874 830,874" fill="#111111"/>');
});

test('a single-cell QR export draws a horizontal arrow beside the label for a middle level', function () {
    // A horizontal (rightward) arrow's tip is on the right (830,874) with
    // the shaft/head extending left to x=587 — see verticalArrowPoints()'s
    // sibling horizontalArrowPoints().
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 3]);
    $cell = $row->cells()->where('flat_number', 2)->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="830,874 708,842 708,861 587,861 587,887 708,887 708,906" fill="#111111"/>');
});

test('a single-cell QR export draws a horizontal arrow beside the label when the row has only one level', function () {
    // A single-level row's one cell is simultaneously its own highest and
    // lowest — there's no up/down direction to indicate, so it gets the same
    // horizontal arrow a genuine mid-level cell does (see
    // BuildsCellQrLabels::cellLevelArrowDirection()).
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    expect($svg)->toContain('<polygon points="830,874 708,842 708,861 587,861 587,887 708,887 708,906" fill="#111111"/>');
});

test('a single-cell QR export uses the configured QR code size instead of the default', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 400, 'qr_code_height' => 500]);
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    // The label canvas width is exactly the configured total-box width, and
    // the embedded QR square is that width minus padding on both sides
    // (padding=20 — see BuildsQrLabels::qrLabelImage()); qr_code_height is a
    // ceiling on the whole label including text, not the QR's own size.
    expect($svg)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="400"')
        ->and($svg)->toContain('<g transform="translate(20,20)"><rect x="0" y="0" width="360" height="360"');
});

test('a single-cell QR export keeps the full slot label visible for large cell/flat numbers', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z']);
    $cell = Cell::factory()->for($row)->create(['cell_number' => 123, 'flat_number' => 456]);

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    // BuildsQrLabels::pickExpandedPrimaryFontSize() must shrink the font
    // rather than let clampLinesToHeight() truncate the label — a fixed
    // large font size would cut the flat number off behind an ellipsis for
    // a label this long, silently hiding real cell coordinates.
    expect($svg)->toContain('Z123·456')
        ->and($svg)->not->toContain('…');
});

test('a single-cell QR export shrinks the QR slightly to keep the label visible when width and height are equal', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 280, 'qr_code_height' => 280]);
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();

    $response = $this->get("/admin/cells/{$cell->id}/export-qr-code");

    $response->assertOk();
    $svg = $response->getContent();
    // Equal width/height used to leave no room below the QR at all, so
    // clampLinesToHeight() dropped the label entirely (see BuildsQrLabels'
    // $minPrimaryZone guard). The QR now shrinks just enough to guarantee
    // the label always has room, rather than disappearing.
    // 280 - 16 (bottom margin) - 46 ($minPrimaryZone) = 218 is the lowest the
    // QR's bottom edge may sit; the unshrunk 240px QR ends at 20+240=260, so
    // it shrinks by 42px to 198px and re-centers at x=(280-198)/2=41.
    expect($svg)->toContain(Cell::slotLabel('Z', $cell->cell_number, $cell->flat_number))
        ->and($svg)->toContain('<g transform="translate(41,20)"><rect x="0" y="0" width="198" height="198"')
        ->and($svg)->not->toContain('width="240" height="240"');
});

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
