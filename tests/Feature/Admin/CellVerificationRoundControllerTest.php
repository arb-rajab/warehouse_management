<?php

use App\Enums\CellLogAction;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use League\Csv\Reader;

test('an admin can finish another workers round and persist its completion without changing its reports or ownership', function () {
    actingAsAdmin();
    $worker = User::factory()->mobileUser()->create();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $noiseRow = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->for($worker)->covering($row)->create();
    $noiseRound = CellVerificationRound::factory()->covering($noiseRow)->create();
    $report = CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $row->cells()->sole()->id,
        'user_id' => $worker->id,
        'is_correct' => false,
    ]);
    $originalReport = $report->fresh()->getAttributes();
    $this->travelTo('2026-10-12 10:00:00');

    $this->post("/admin/cell-verification-rounds/{$round->id}/complete?is_correct=0&page=2")
        ->assertRedirect("/admin/cell-verification-rounds/{$round->id}?is_correct=0&page=2");

    expect($round->fresh()->completed_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($round->fresh()->user_id)->toBe($worker->id)
        ->and($round->rows()->pluck('rows.id')->all())->toBe([$row->id])
        ->and($report->fresh()->getAttributes())->toBe($originalReport)
        ->and($noiseRound->fresh()->completed_at)->toBeNull()
        ->and(Row::query()->whereKey($row->id)->underActiveVerification()->exists())->toBeFalse()
        ->and(Row::query()->whereKey($noiseRow->id)->underActiveVerification()->exists())->toBeTrue();

    $this->get("/admin/cell-verification-rounds/{$round->id}")->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('round.completed_at', $round->fresh()->completed_at->toIso8601String())
            ->where('round.reports_count', 1)
    );
});

test('an admin can finish an unchecked round and its rows become available for pallet actions and new rounds', function () {
    actingAsAdmin();
    $worker = User::factory()->mobileUser()->create();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $cell = $row->cells()->sole();
    $product = Product::factory()->boxesCount(10)->create();
    $round = CellVerificationRound::factory()->for($worker)->covering($row)->create();
    $payload = ['product_id' => $product->id, 'expiration_date' => '2027-01-01'];

    $this->post("/admin/cells/{$cell->id}/pallet", $payload)
        ->assertSessionHasErrors('action');
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeFalse();

    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertRedirect();
    expect($round->fresh()->isCompleted())->toBeTrue()
        ->and($round->reports()->count())->toBe(0);

    $this->post("/admin/cells/{$cell->id}/pallet", $payload)->assertSessionHasNoErrors();
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeTrue();

    actingAsMobileUser($worker);
    $this->postJson('/api/v1/cell-verification-rounds', ['row_ids' => [$row->id]])->assertCreated();
    expect(CellVerificationRound::query()->unfinished()->count())->toBe(1);
});

test('repeated admin and worker completion requests retain the first completion timestamp and cannot reopen the round', function () {
    actingAsAdmin();
    $worker = User::factory()->mobileUser()->create();
    $round = CellVerificationRound::factory()->for($worker)->create();
    $this->travelTo('2026-10-12 10:00:00');
    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertRedirect();

    $this->travelTo('2026-10-12 11:00:00');
    $this->post("/admin/cell-verification-rounds/{$round->id}/complete", ['completed_at' => null, 'user_id' => 999999])
        ->assertRedirect();
    actingAsMobileUser($worker);
    $this->postJson("/api/v1/cell-verification-rounds/{$round->id}/complete")->assertOk();

    expect($round->fresh()->completed_at->toDateTimeString())->toBe('2026-10-12 10:00:00')
        ->and($round->fresh()->user_id)->toBe($worker->id);
});

test('a worker cannot add reports after an admin finishes the round', function () {
    actingAsAdmin();
    $worker = User::factory()->mobileUser()->create();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->for($worker)->covering($row)->create();
    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertRedirect();
    $completedAt = $round->fresh()->completed_at;

    actingAsMobileUser($worker);
    $this->postJson('/api/v1/cell-verification-reports', [
        'cell_verification_round_id' => $round->id,
        'cell_id' => $row->cells()->sole()->id,
        'is_correct' => true,
    ])->assertConflict()->assertJson(['error_code' => 'verification_round_completed']);

    expect($round->reports()->count())->toBe(0)
        ->and($round->fresh()->completed_at->eq($completedAt))->toBeTrue();
});

test('a non-admin cannot finish even their own round through the admin action and nothing changes', function () {
    $worker = actingAsMobilePanelUser();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->for($worker)->covering($row)->create();

    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertForbidden();

    expect($round->fresh()->completed_at)->toBeNull()
        ->and(Row::query()->whereKey($row->id)->underActiveVerification()->exists())->toBeTrue();
});

test('a guest cannot finish a round through the admin action and nothing changes', function () {
    $round = CellVerificationRound::factory()->create();

    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertRedirect(route('login'));

    expect($round->fresh()->completed_at)->toBeNull();
});

test('admin completion of a missing round returns a 404 and does not change another round', function () {
    actingAsAdmin();
    $round = CellVerificationRound::factory()->create();

    $this->post('/admin/cell-verification-rounds/999999/complete')->assertNotFound();

    expect($round->fresh()->completed_at)->toBeNull();
});

test('admin completion rolls back the timestamp and keeps the row frozen if persistence fails', function () {
    actingAsAdmin();
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->covering($row)->create();
    Event::listen('eloquent.updated: '.CellVerificationRound::class, function (): void {
        throw new RuntimeException('Completion persistence failed');
    });

    $this->post("/admin/cell-verification-rounds/{$round->id}/complete")->assertServerError();

    expect($round->fresh()->completed_at)->toBeNull()
        ->and(Row::query()->whereKey($row->id)->underActiveVerification()->exists())->toBeTrue();
});

test('an authenticated admin can view the cell verification rounds list with every property the table renders', function () {
    $admin = actingAsAdmin();
    $admin->update(['name' => 'Zoe Admin']);

    $worker = User::factory()->mobileUser()->create(['name' => 'Ada Reporter']);
    $rowA = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $rowB = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]);
    Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]); // noise: not in this round

    $round = CellVerificationRound::factory()->completed()->covering($rowB, $rowA)->create(['user_id' => $worker->id]);

    // Reported against a cell inside the round's own coverage — which is also
    // what keeps the report factory from generating a third row of its own,
    // whose faker-drawn letter could collide with the ones hardcoded above.
    // user_id is pinned to the round's own worker too, so the report factory
    // doesn't draw its own extra users, which would make the filterOptions.users
    // assertion below non-deterministic.
    CellVerificationReport::factory()->count(2)->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $rowA->cells()->first()->id,
        'user_id' => $worker->id,
    ]);

    $response = $this->get('/admin/cell-verification-rounds');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/CellVerificationRounds/Index')
            ->has('rounds.data', 1)
            ->has('rounds.data.0', fn (Assert $roundProp) => $roundProp
                ->where('id', $round->id)
                ->where('started_at', $round->created_at->toIso8601String())
                ->where('completed_at', $round->completed_at->toIso8601String())
                ->where('reports_count', 2)
                ->where('rows', [
                    ['id' => $rowA->id, 'letter' => 'A'],
                    ['id' => $rowB->id, 'letter' => 'B'],
                ])
                ->has('user', fn (Assert $userProp) => $userProp
                    ->where('id', $worker->id)
                    ->where('name', 'Ada Reporter')
                )
            )
            ->where('filterOptions.users', [
                ['id' => $worker->id, 'name' => 'Ada Reporter'],
                ['id' => $admin->id, 'name' => 'Zoe Admin'],
            ])
    );
});

test('an unauthenticated caller is redirected to login when viewing the cell verification rounds list', function () {
    $response = $this->get('/admin/cell-verification-rounds');

    $response->assertRedirect(route('login'));
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

test('filtering the rounds listing by created_within_days together with a date range is rejected', function () {
    actingAsAdmin();

    $response = $this->get('/admin/cell-verification-rounds?created_within_days=7&date_from=2026-06-01');

    $response->assertSessionHasErrors('created_within_days');
});

test('an out-of-range sort_direction is rejected on the rounds listing', function () {
    actingAsAdmin();

    $this->get('/admin/cell-verification-rounds?sort_direction=sideways')
        ->assertSessionHasErrors('sort_direction');
});

test('filtering a rounds reports listing by created_within_days together with a date range is rejected', function () {
    actingAsAdmin();
    $round = CellVerificationRound::factory()->create();

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}?created_within_days=7&date_from=2026-06-01");

    $response->assertSessionHasErrors('created_within_days');
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
    $round = CellVerificationRound::factory()->covering($row)->create();
    $reporter = User::factory()->mobileUser()->create(['name' => 'Ada Reporter']);
    $product = Product::factory()->imageUrl(null)->boxesCount(5)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
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
            ->where('round.rows', [['id' => $row->id, 'letter' => 'C']])
            ->where('filterOptions.rows', [['id' => $row->id, 'letter' => 'C']])
            ->where('filterOptions.maxColumnNumber', 1)
            ->has('filterOptions.products', 0)
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
                        ->where('ar_name', 'ودجات')
                        ->where('image_url', null)
                        ->where('boxes_count', 5)
                        ->where('active', true)
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
                ->where('round_completed', false)
                ->where('accepted_at', null)
                ->where('accepted_by', null)
                ->where('created_at', $report->created_at->toIso8601String())
            )
    );
});

test('an accepted report on a round ships when and by whom it was accepted, alongside its unchanged discrepancy', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->completed()->covering($row)->create();
    $acceptedBy = User::factory()->create(['name' => 'Zoe Admin']);
    $report = CellVerificationReport::factory()->accepted()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $row->cells()->first()->id,
        'accepted_at' => '2026-09-20 09:15:00',
        'accepted_by' => $acceptedBy->id,
    ]);

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $report->id)
            ->where('reports.data.0.is_correct', false)
            ->where('reports.data.0.expected.cell_state', 'full')
            ->where('reports.data.0.reported.cell_state', 'empty')
            ->where('reports.data.0.round_completed', true)
            ->where('reports.data.0.accepted_at', $report->fresh()->accepted_at->toIso8601String())
            ->where('reports.data.0.accepted_by', ['id' => $acceptedBy->id, 'name' => 'Zoe Admin'])
    );
});

test('a round\'s report snapshots ship both raw product name columns on each half', function () {
    actingAsAdmin();

    $row = Row::factory()->create(['letter' => 'C', 'cells_count' => 1, 'flats_count' => 1]);
    $round = CellVerificationRound::factory()->create();
    $expected = Product::factory()->imageUrl(null)->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    // The reported half is a separate eager load, so it needs its own proof —
    // and an untranslated product there covers the empty-string case that the
    // frontend resolver falls back on.
    $reported = Product::factory()->imageUrl(null)->create(['name' => 'Gadgets', 'ar_name' => '']);

    CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $row->cells()->first()->id,
        'is_correct' => false,
        'expected_cell_state' => CellState::Full,
        'expected_product_id' => $expected->id,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $reported->id,
    ]);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/cell-verification-rounds/{$round->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('reports.data', 1)
            ->where('reports.data.0.expected.product.name', 'Widgets')
            ->where('reports.data.0.expected.product.ar_name', 'ودجات')
            ->where('reports.data.0.reported.product.name', 'Gadgets')
            ->where('reports.data.0.reported.product.ar_name', '')
    );
});

test('an unauthenticated caller is redirected to login when viewing a single round', function () {
    $round = CellVerificationRound::factory()->create();

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}");

    $response->assertRedirect(route('login'));
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

test('the reports csv keeps the store\'s base product name even under the Arabic locale', function () {
    // The export is data, not UI: its column headers are untranslated machine
    // names, so its product column stays on the store's stable base `name`
    // and never emits `ar_name`, unlike every rendered label — which now ships
    // both columns for the frontend to choose from.
    // See .ai/rules/shared-database.md.
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $product = Product::factory()->create(['name' => 'Widgets', 'ar_name' => 'ودجات']);
    CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'expected_product_id' => $product->id,
    ]);

    $response = $this->withSession(['locale' => 'ar'])->get("/admin/cell-verification-rounds/{$round->id}/export");

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Widgets');
    expect($csv)->not->toContain('ودجات');
});

test('an unauthenticated caller is redirected to login when exporting a rounds reports csv', function () {
    $round = CellVerificationRound::factory()->create();

    $response = $this->get("/admin/cell-verification-rounds/{$round->id}/export");

    $response->assertRedirect(route('login'));
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

test('exporting a rounds reports csv escapes cells a spreadsheet would run as a formula', function (string $value) {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'expected_product_id' => Product::factory()->create(['name' => $value]),
        'note' => $value,
    ]);

    $csv = $this->get("/admin/cell-verification-rounds/{$round->id}/export")->streamedContent();

    $rows = iterator_to_array(Reader::createFromString($csv)->setHeaderOffset(0)->getRecords(), false);

    // EscapeFormula prefixes a single quote, so no cell begins with the trigger.
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['note'])->toBe("'{$value}")
        ->and($rows[0]['expected_product'])->toBe("'{$value}");
})->with([
    'equals' => ['=HYPERLINK("http://evil")'],
    'plus' => ['+1+1'],
    'minus' => ['-2+3'],
    'at' => ['@SUM(A1)'],
]);

test('exporting a rounds reports csv leaves ordinary text untouched', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'note' => 'Plain note',
    ]);

    expect($this->get("/admin/cell-verification-rounds/{$round->id}/export")->streamedContent())
        ->toContain('Plain note')
        ->not->toContain("'Plain note");
});

test('exporting a rounds reports csv eager loads report details instead of an N+1 per report', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->create();
    $expectedProduct = Product::factory()->create();
    $reportedProduct = Product::factory()->create();
    CellVerificationReport::factory()->count(10)->create([
        'cell_verification_round_id' => $round->id,
        'expected_product_id' => $expectedProduct->id,
        'reported_product_id' => $reportedProduct->id,
    ]);

    DB::enableQueryLog();
    $response = $this->get("/admin/cell-verification-rounds/{$round->id}/export");
    $csv = $response->streamedContent();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect(substr_count($csv, "\n"))->toBe(11); // header + 10 report rows
    // Without eager loading, 10 reports touching cell->row, expectedProduct and
    // reportedProduct each would push this well past 30 queries.
    expect($queryCount)->toBeLessThan(15);
});

test('exporting a rounds reports csv includes when each report was accepted', function () {
    actingAsAdmin();

    $round = CellVerificationRound::factory()->completed()->create();
    $accepted = CellVerificationReport::factory()->accepted()->create([
        'cell_verification_round_id' => $round->id,
        'accepted_at' => '2026-09-20 09:15:00',
    ]);
    CellVerificationReport::factory()->incorrect()->create(['cell_verification_round_id' => $round->id]); // noise: not accepted

    $csv = $this->get("/admin/cell-verification-rounds/{$round->id}/export")->streamedContent();

    $lines = array_values(array_filter(explode("\n", $csv)));
    expect(str_getcsv($lines[0]))->toContain('accepted_at');
    expect($csv)->toContain($accepted->fresh()->accepted_at->toIso8601String());
    expect(substr_count($csv, $accepted->fresh()->accepted_at->toIso8601String()))->toBe(1);
});

/**
 * A completed round over a single-cell row (flat 2, so store() fills it Full
 * rather than opening it) with one incorrect report against that cell.
 *
 * @param  array<string, mixed>  $reportAttributes
 * @return array{cell: Cell, report: CellVerificationReport}
 */
function incorrectReportOnFreshCell(array $reportAttributes): array
{
    $row = Row::factory()->create(['cells_count' => 1, 'flats_count' => 2]);
    $cell = $row->cells()->where('flat_number', 2)->sole();
    $round = CellVerificationRound::factory()->completed()->covering($row)->create();

    $report = CellVerificationReport::factory()->create([
        'cell_verification_round_id' => $round->id,
        'cell_id' => $cell->id,
        'is_correct' => false,
        ...$reportAttributes,
    ]);

    return ['cell' => $cell, 'report' => $report];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function palletInCell(Cell $cell, CellState $state, array $attributes): Pallet
{
    $pallet = Pallet::factory()->create(['cell_id' => $cell->id, ...$attributes]);

    // After create(): PalletFactory's afterCreating hook resets the cell to Full.
    $cell->update(['state' => $state]);

    return $pallet;
}

test('accepting a report of an empty cell empties the stored pallet and records the acceptance without touching the discrepancy', function () {
    $admin = actingAsAdmin();
    $product = Product::factory()->boxesCount(10)->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Full,
        'expected_product_id' => $product->id,
        'expected_boxes_count' => 10,
        'expected_expiration_date' => '2026-12-01',
        'reported_cell_state' => CellState::Empty,
        'reported_product_id' => null,
        'reported_boxes_count' => null,
        'reported_expiration_date' => null,
    ]);
    $pallet = palletInCell($cell, CellState::Full, ['product_id' => $product->id, 'remaining_boxes' => 10, 'expiration_date' => '2026-12-01']);
    $this->travelTo('2026-09-27 12:00:00');

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertRedirect("/admin/cell-verification-rounds/{$report->cell_verification_round_id}");
    $response->assertSessionHasNoErrors();
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect(Pallet::find($pallet->id))->toBeNull();

    $log = CellStatusLog::query()->where('cell_id', $cell->id)->sole();
    expect($log->action)->toBe(CellLogAction::Emptied);
    expect($log->user_id)->toBe($admin->id);
    expect($log->note)->toBe("Accepted from verification report #{$report->id}");

    $fresh = $report->fresh();
    expect($fresh->accepted_at->toDateTimeString())->toBe('2026-09-27 12:00:00');
    expect($fresh->accepted_by)->toBe($admin->id);
    expect($fresh->is_correct)->toBeFalse();
    expect($fresh->expected_cell_state)->toBe(CellState::Full);
    expect($fresh->expected_product_id)->toBe($product->id);
    expect($fresh->reported_cell_state)->toBe(CellState::Empty);
});

test('accepting a report of an opened pallet in an empty cell stores and opens it with the reported contents', function () {
    actingAsAdmin();
    $product = Product::factory()->boxesCount(20)->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'expected_product_id' => null,
        'expected_boxes_count' => null,
        'expected_expiration_date' => null,
        'reported_cell_state' => CellState::Opened,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 7,
        'reported_expiration_date' => '2027-01-15',
    ]);

    $this->post("/admin/cell-verification-reports/{$report->id}/accept")->assertSessionHasNoErrors();

    expect($cell->fresh()->state)->toBe(CellState::Opened);
    $pallet = Pallet::query()->where('cell_id', $cell->id)->sole();
    expect($pallet->product_id)->toBe($product->id);
    expect($pallet->remaining_boxes)->toBe(7);
    expect($pallet->expiration_date->toDateString())->toBe('2027-01-15');
    expect(CellStatusLog::query()->where('cell_id', $cell->id)->orderBy('id')->pluck('action')->all())
        ->toBe([CellLogAction::Stored, CellLogAction::Opened]);
    expect($report->fresh()->accepted_at)->not->toBeNull();
});

test('accepting a report of a full pallet where the system has an opened one replaces it with the reported pallet', function () {
    actingAsAdmin();
    $storedProduct = Product::factory()->boxesCount(10)->create();
    $reportedProduct = Product::factory()->boxesCount(20)->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Opened,
        'expected_product_id' => $storedProduct->id,
        'expected_boxes_count' => 4,
        'expected_expiration_date' => null,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $reportedProduct->id,
        'reported_boxes_count' => 20,
        'reported_expiration_date' => '2027-02-01',
    ]);
    $oldPallet = palletInCell($cell, CellState::Opened, ['product_id' => $storedProduct->id, 'remaining_boxes' => 4, 'expiration_date' => null]);

    $this->post("/admin/cell-verification-reports/{$report->id}/accept")->assertSessionHasNoErrors();

    expect($cell->fresh()->state)->toBe(CellState::Full);
    expect(Pallet::find($oldPallet->id))->toBeNull();
    $pallet = Pallet::query()->where('cell_id', $cell->id)->sole();
    expect($pallet->product_id)->toBe($reportedProduct->id);
    expect($pallet->remaining_boxes)->toBe(20);
    expect($pallet->expiration_date->toDateString())->toBe('2027-02-01');
    expect(CellStatusLog::query()->where('cell_id', $cell->id)->orderBy('id')->pluck('action')->all())
        ->toBe([CellLogAction::Emptied, CellLogAction::Stored]);
});

test('accepting a report that only differs in the pallets contents corrects them in place', function () {
    actingAsAdmin();
    $product = Product::factory()->boxesCount(10)->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Full,
        'expected_product_id' => $product->id,
        'expected_boxes_count' => 10,
        'expected_expiration_date' => '2026-12-01',
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 6,
        'reported_expiration_date' => '2026-11-01',
    ]);
    $pallet = palletInCell($cell, CellState::Full, ['product_id' => $product->id, 'remaining_boxes' => 10, 'expiration_date' => '2026-12-01']);

    $this->post("/admin/cell-verification-reports/{$report->id}/accept")->assertSessionHasNoErrors();

    $fresh = $pallet->fresh();
    expect($fresh->remaining_boxes)->toBe(6);
    expect($fresh->expiration_date->toDateString())->toBe('2026-11-01');
    expect($cell->fresh()->state)->toBe(CellState::Full);
    expect($report->fresh()->accepted_at)->not->toBeNull();
});

test('accepting a report keeps the rounds filters and page on the redirect', function () {
    actingAsAdmin();
    ['report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Empty,
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept?is_correct=0&page=2");

    $response->assertRedirect("/admin/cell-verification-rounds/{$report->cell_verification_round_id}?is_correct=0&page=2");
});

test('accepting a report from its reporters page redirects back there, keeping that pages query', function () {
    actingAsAdmin();
    ['report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Empty,
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept?reports_page=2", ['return_to' => 'user']);

    $response->assertRedirect("/admin/users/{$report->user_id}?reports_page=2");
    $response->assertSessionHasNoErrors();
    expect($report->fresh()->accepted_at)->not->toBeNull();
});

test('a refused accept from the reporters page shows its error back on that page', function () {
    actingAsAdmin();
    ['report' => $report] = incorrectReportOnFreshCell([
        'is_correct' => true,
        'expected_cell_state' => CellState::Empty,
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept", ['return_to' => 'user']);

    $response->assertRedirect("/admin/users/{$report->user_id}");
    $response->assertSessionHasErrors(['action' => __('messages.verification_report_already_correct')]);
});

test('accepting a report with an unknown return_to is rejected without changing anything', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 5,
        'reported_expiration_date' => '2027-01-01',
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept", ['return_to' => 'dashboard']);

    $response->assertSessionHasErrors('return_to');
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeFalse();
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('a report cannot be accepted twice', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 5,
        'reported_expiration_date' => '2027-01-01',
    ]);
    $firstAcceptor = User::factory()->create();
    $report->update(['accepted_at' => '2026-09-20 09:15:00', 'accepted_by' => $firstAcceptor->id]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertRedirect("/admin/cell-verification-rounds/{$report->cell_verification_round_id}");
    $response->assertSessionHasErrors(['action' => __('messages.verification_report_already_accepted')]);
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeFalse();
    expect($report->fresh()->accepted_by)->toBe($firstAcceptor->id);
    expect($report->fresh()->accepted_at->toDateTimeString())->toBe('2026-09-20 09:15:00');
});

test('a correct report cannot be accepted', function () {
    actingAsAdmin();
    ['report' => $report] = incorrectReportOnFreshCell([
        'is_correct' => true,
        'expected_cell_state' => CellState::Empty,
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertSessionHasErrors(['action' => __('messages.verification_report_already_correct')]);
    expect($report->fresh()->accepted_at)->toBeNull();
    expect(CellStatusLog::query()->count())->toBe(0);
});

test('a report whose reported product has since been deleted cannot be accepted', function () {
    actingAsAdmin();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => null,
        'reported_boxes_count' => 5,
        'reported_expiration_date' => '2027-01-01',
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertSessionHasErrors(['action' => __('messages.verification_report_incomplete')]);
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('a report cannot be accepted once its cell has changed since it was verified', function () {
    actingAsAdmin();
    $product = Product::factory()->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 5,
        'reported_expiration_date' => '2027-01-01',
    ]);
    // Someone stored a different pallet in the cell after the report was made.
    $newerPallet = palletInCell($cell, CellState::Full, ['product_id' => Product::factory(), 'remaining_boxes' => 3]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertSessionHasErrors(['action' => __('messages.cell_changed_since_verification_report')]);
    expect($newerPallet->fresh()->remaining_boxes)->toBe(3);
    expect($newerPallet->fresh()->product_id)->not->toBe($product->id);
    expect($report->fresh()->accepted_at)->toBeNull();
    expect(CellStatusLog::query()->count())->toBe(0);
});

test('a report cannot be accepted while its cells row is being verified by an unfinished round', function () {
    actingAsAdmin();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Full,
        'expected_product_id' => null,
        'expected_boxes_count' => null,
        'reported_cell_state' => CellState::Empty,
    ]);
    $cell->update(['state' => CellState::Full]);
    CellVerificationRound::factory()->covering($cell->row)->create(); // unfinished

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertSessionHasErrors(['action' => __('messages.cell_in_active_round')]);
    expect($cell->fresh()->state)->toBe(CellState::Full);
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('a failure partway through accepting a report rolls back every pallet change', function () {
    actingAsAdmin();
    $product = Product::factory()->boxesCount(10)->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        // Stored fine, then rejected when the contents are set to zero boxes.
        'reported_boxes_count' => 0,
        'reported_expiration_date' => '2027-01-01',
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertSessionHasErrors(['action' => __('messages.insufficient_boxes_remaining')]);
    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeFalse();
    expect(CellStatusLog::query()->count())->toBe(0);
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('an unauthenticated caller is redirected to login when accepting a report', function () {
    ['report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Empty,
    ]);

    $response = $this->post("/admin/cell-verification-reports/{$report->id}/accept");

    $response->assertRedirect(route('login'));
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('a non-admin cannot accept a report', function () {
    actingAsMobilePanelUser();
    $product = Product::factory()->create();
    ['cell' => $cell, 'report' => $report] = incorrectReportOnFreshCell([
        'expected_cell_state' => CellState::Empty,
        'reported_cell_state' => CellState::Full,
        'reported_product_id' => $product->id,
        'reported_boxes_count' => 5,
        'reported_expiration_date' => '2027-01-01',
    ]);

    $this->post("/admin/cell-verification-reports/{$report->id}/accept")->assertForbidden();

    expect($cell->fresh()->state)->toBe(CellState::Empty);
    expect(Pallet::query()->where('cell_id', $cell->id)->exists())->toBeFalse();
    expect($report->fresh()->accepted_at)->toBeNull();
});

test('accepting a non-existent report returns a 404', function () {
    actingAsAdmin();

    $this->post('/admin/cell-verification-reports/999999/accept')->assertNotFound();
});
