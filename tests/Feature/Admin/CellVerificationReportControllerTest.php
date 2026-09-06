<?php

use App\Enums\CellState;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell verification reports list with every property the table renders', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $round = CellVerificationRound::factory()->create();
    $reporter = User::factory()->mobileUser()->create(['name' => 'Ada Reporter']);
    $product = Product::factory()->create(['name' => 'Widgets', 'image_url' => null, 'boxes_count' => 5]);
    $report = CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'user_id' => $reporter->id,
        'is_correct' => false,
        'expected_cell_state' => CellState::Full,
        'expected_product_id' => $product->id,
        'expected_boxes_count' => 5,
        'expected_expiration_date' => '2026-12-01',
        'reported_cell_state' => CellState::Empty,
        'reported_product_id' => null,
        'reported_boxes_count' => null,
        'reported_expiration_date' => null,
        'note' => 'Looks fine.',
    ]);

    $response = $this->get('/admin/cell-verification-reports');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellVerificationReports/Index')
            ->has('reports.data', 1)
            ->has('reports.data.0', fn (Assert $reportProp) => $reportProp
                ->where('id', $report->id)
                ->where('cell_verification_round_id', $round->id)
                ->where('is_correct', false)
                ->has('cell', fn (Assert $cellProp) => $cellProp
                    ->where('row_letter', 'C')
                    ->where('cell_number', 1)
                    ->where('flat_number', 1)
                )
                ->has('expected', fn (Assert $expectedProp) => $expectedProp
                    ->where('cell_state', 'full')
                    ->has('product', fn (Assert $productProp) => $productProp
                        ->where('id', $product->id)
                        ->where('name', 'Widgets')
                        ->where('image_url', null)
                        ->where('boxes_count', 5)
                    )
                    ->where('boxes_count', 5)
                    ->where('expiration_date', '2026-12-01')
                )
                ->has('reported', fn (Assert $reportedProp) => $reportedProp
                    ->where('cell_state', 'empty')
                    ->where('product', null)
                    ->where('boxes_count', null)
                    ->where('expiration_date', null)
                )
                ->where('note', 'Looks fine.')
                ->has('user', fn (Assert $userProp) => $userProp
                    ->where('id', $reporter->id)
                    ->where('name', 'Ada Reporter')
                )
                ->where('created_at', $report->created_at->toIso8601String())
            )
    );
});

test('a non-admin cannot view the cell verification reports list', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/cell-verification-reports')->assertForbidden();
});

test('a non-admin cannot export the cell verification reports csv', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/cell-verification-reports/export')->assertForbidden();
});

test('the round filter excludes reports from other rounds', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $otherRound = CellVerificationRound::factory()->create(); // noise
    $report = CellVerificationReport::factory()->create(['cell_verification_round_id' => $round->id]);
    CellVerificationReport::factory()->create(['cell_verification_round_id' => $otherRound->id]);

    $response = $this->get("/admin/cell-verification-reports?cell_verification_round_id={$round->id}");

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $report->id)
    );
});

test('the user filter excludes reports from other users', function () {
    actingAsAdmin();

    $user = User::factory()->mobileUser()->create();
    $otherUser = User::factory()->mobileUser()->create(); // noise
    $report = CellVerificationReport::factory()->create(['user_id' => $user->id]);
    CellVerificationReport::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->get("/admin/cell-verification-reports?user_id[]={$user->id}");

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $report->id)
    );
});

test('the is_correct filter excludes reports of the other correctness', function () {
    actingAsAdmin();

    $incorrect = CellVerificationReport::factory()->incorrect()->create();
    CellVerificationReport::factory()->create(['is_correct' => true]); // noise

    $response = $this->get('/admin/cell-verification-reports?is_correct=0');

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $incorrect->id)
    );
});

test('the listing paginates beyond one page', function () {
    actingAsAdmin();

    CellVerificationReport::factory()->count(25)->create();

    $response = $this->get('/admin/cell-verification-reports?per_page=10');

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 10)
            ->where('reports.meta.total', 25)
    );
});

test('exporting csv only includes the filtered rows', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $otherRound = CellVerificationRound::factory()->create();
    $report = CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'note' => 'Included row',
    ]);
    CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $otherRound->id,
        'note' => 'Excluded row',
    ]);

    $response = $this->get("/admin/cell-verification-reports/export?cell_verification_round_id={$round->id}");

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Included row');
    expect($csv)->not->toContain('Excluded row');
    expect($csv)->toContain((string) $report->id);
});
