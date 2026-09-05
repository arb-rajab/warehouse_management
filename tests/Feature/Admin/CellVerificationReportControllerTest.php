<?php

use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Row;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the cell verification reports list', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->first();
    $round = CellVerificationRound::factory()->create();
    $report = CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'note' => 'Looks fine.',
    ]);

    $response = $this->get('/admin/cell-verification-reports');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellVerificationReports/Index')
            ->has('reports.data', 1)
            ->where('reports.data.0.id', $report->id)
            ->where('reports.data.0.note', 'Looks fine.')
    );
});

test('a non-admin cannot view the cell verification reports list', function () {
    actingAsMobilePanelUser();

    $this->get('/admin/cell-verification-reports')->assertForbidden();
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
