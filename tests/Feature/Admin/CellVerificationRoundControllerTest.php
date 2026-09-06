<?php

use App\Enums\CellState;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell verification rounds list with every property the table renders', function () {
    actingAsAdmin();

    $worker = User::factory()->mobileUser()->create(['name' => 'Ada Reporter']);
    $round = CellVerificationRound::factory()->completed()->create(['user_id' => $worker->id]);
    CellVerificationReport::factory()->count(2)->create(['cell_verification_round_id' => $round->id]);

    $response = $this->get('/admin/cell-verification-rounds');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellVerificationRounds/Index')
            ->has('rounds.data', 1)
            ->has('rounds.data.0', fn (Assert $roundProp) => $roundProp
                ->where('id', $round->id)
                ->where('started_at', $round->created_at->toIso8601String())
                ->where('completed_at', $round->completed_at->toIso8601String())
                ->where('reports_count', 2)
                ->has('user', fn (Assert $userProp) => $userProp
                    ->where('id', $worker->id)
                    ->where('name', 'Ada Reporter')
                )
            )
    );
});

test('a non-admin cannot view the cell verification rounds list', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/cell-verification-rounds')->assertForbidden();
});

test('the user filter excludes rounds from other users', function () {
    actingAsAdmin();

    $user = User::factory()->mobileUser()->create();
    $otherUser = User::factory()->mobileUser()->create(); // noise
    $round = CellVerificationRound::factory()->create(['user_id' => $user->id]);
    CellVerificationRound::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->get("/admin/cell-verification-rounds?user_id[]={$user->id}");

    $response->assertInertia(
        fn (Assert $page) => $page->has('rounds.data', 1)
            ->where('rounds.data.0.id', $round->id)
    );
});

test('the completed filter excludes rounds of the other completion state', function () {
    actingAsAdmin();

    $completed = CellVerificationRound::factory()->completed()->create();
    CellVerificationRound::factory()->create(); // noise: unfinished

    $response = $this->get('/admin/cell-verification-rounds?completed=1');

    $response->assertInertia(
        fn (Assert $page) => $page->has('rounds.data', 1)
            ->where('rounds.data.0.id', $completed->id)
    );
});

test('the rounds listing paginates beyond one page', function () {
    actingAsAdmin();

    CellVerificationRound::factory()->count(25)->create();

    $response = $this->get('/admin/cell-verification-rounds?per_page=10');

    $response->assertInertia(
        fn (Assert $page) => $page->has('rounds.data', 10)
            ->where('rounds.meta.total', 25)
    );
});

test('an authenticated admin can view a single round with its own reports and every property the table renders', function () {
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

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellVerificationRounds/Show')
            ->where('round.id', $round->id)
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

test('a non-admin cannot view a single round', function () {
    actingAsMobilePanelUser();

    $round = CellVerificationRound::factory()->create();

    $this->get("/admin/cell-verification-rounds/{$round->id}")->assertForbidden();
});

test('viewing a non-existent round returns a 404', function () {
    actingAsAdmin();

    $this->get('/admin/cell-verification-rounds/999999')->assertNotFound();
});

test('a round only shows its own reports, excluding another rounds reports', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $otherRound = CellVerificationRound::factory()->create();
    $report = CellVerificationReport::factory()->create(['cell_verification_round_id' => $round->id]);
    CellVerificationReport::factory()->create(['cell_verification_round_id' => $otherRound->id]); // noise

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}");

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $report->id)
    );
});

test('the is_correct filter on a round show excludes reports of the other correctness', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $incorrect = CellVerificationReport::factory()->incorrect()->create(['cell_verification_round_id' => $round->id]);
    CellVerificationReport::factory()->create(['cell_verification_round_id' => $round->id, 'is_correct' => true]); // noise

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}?is_correct=0");

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $incorrect->id)
    );
});

test('a rounds reports listing paginates beyond one page', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    CellVerificationReport::factory()->count(25)->create(['cell_verification_round_id' => $round->id]);

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}?per_page=10");

    $response->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 10)
            ->where('reports.meta.total', 25)
    );
});

test('a non-admin cannot export a rounds reports csv', function () {
    actingAsMobilePanelUser();

    $round = CellVerificationRound::factory()->create();

    $this->get("/admin/cell-verification-rounds/{$round->id}/export")->assertForbidden();
});

test('exporting a non-existent rounds reports csv returns a 404', function () {
    actingAsAdmin();

    $this->get('/admin/cell-verification-rounds/999999/export')->assertNotFound();
});

test('exporting a rounds reports csv only includes that rounds filtered rows', function () {
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

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}/export");

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Included row');
    expect($csv)->not->toContain('Excluded row');
    expect($csv)->toContain((string) $report->id);
});
