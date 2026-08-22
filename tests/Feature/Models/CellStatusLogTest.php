<?php

use App\Enums\CellLogAction;
use App\Enums\CellLogFlagReason;
use App\Enums\CellState;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\CellStatusLogFlag;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

test('a cell status log belongs to its cell', function () {
    $cell = Cell::factory()->create();
    $otherCell = Cell::factory()->create();
    $log = CellStatusLog::factory()->create(['cell_id' => $cell->id]);

    expect($log->cell->id)->toBe($cell->id);
    expect($log->cell->id)->not->toBe($otherCell->id);
});

test('a cell status log resolves its related cell for a transfer entry', function () {
    $cell = Cell::factory()->create();
    $relatedCell = Cell::factory()->create();
    $log = CellStatusLog::factory()->create([
        'cell_id' => $cell->id,
        'related_cell_id' => $relatedCell->id,
    ]);

    expect($log->relatedCell)->not->toBeNull();
    expect($log->relatedCell->id)->toBe($relatedCell->id);
    expect($log->relatedCell->id)->not->toBe($cell->id);
});

test('a cell status log has no related cell for a plain status update', function () {
    $log = CellStatusLog::factory()->create(['related_cell_id' => null]);

    expect($log->relatedCell)->toBeNull();
});

test('a cell status log belongs to its product', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $log = CellStatusLog::factory()->create(['product_id' => $product->id]);

    expect($log->product->id)->toBe($product->id);
    expect($log->product->id)->not->toBe($otherProduct->id);
});

test('a cell status log belongs to its pallet', function () {
    $pallet = Pallet::factory()->create();
    $otherPallet = Pallet::factory()->create();
    $log = CellStatusLog::factory()->create(['pallet_id' => $pallet->id]);

    expect($log->pallet->id)->toBe($pallet->id);
    expect($log->pallet->id)->not->toBe($otherPallet->id);
});

test('a cell status log keeps its pallet_id but resolves a null pallet once the pallet is deleted', function () {
    $pallet = Pallet::factory()->create();
    $palletId = $pallet->id;
    $log = CellStatusLog::factory()->create(['pallet_id' => $palletId]);

    $pallet->delete();

    $fresh = $log->fresh();

    expect($fresh->pallet_id)->toBe($palletId);
    expect($fresh->pallet)->toBeNull();
});

test('a cell status log belongs to the user who performed it', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $log = CellStatusLog::factory()->create(['user_id' => $user->id]);

    expect($log->user->id)->toBe($user->id);
    expect($log->user->id)->not->toBe($otherUser->id);
});

test('a cell status log has many flags, excluding another logs flags', function () {
    $log = CellStatusLog::factory()->create();
    $flag = CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id, 'reason' => CellLogFlagReason::OffHours]);

    $otherLog = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $otherLog->id]);

    expect($log->flags)->toHaveCount(1);
    expect($log->flags->first()->id)->toBe($flag->id);
});

test('flagged is false when a log has no flags', function () {
    $log = CellStatusLog::factory()->create();

    expect($log->flagged)->toBeFalse();
});

test('flagged is true when a log has at least one flag, via a dedicated exists query when nothing is preloaded', function () {
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    expect($log->fresh()->flagged)->toBeTrue();
});

test('flagged reads an already-loaded flags relation instead of running another query', function () {
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $loaded = CellStatusLog::query()->with('flags')->find($log->id);

    expect($loaded->relationLoaded('flags'))->toBeTrue();
    expect($loaded->flagged)->toBeTrue();
});

test('flagged reads a flags_count alias when present, without a flags relation loaded', function () {
    $log = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $log->id]);

    $counted = CellStatusLog::query()->withCount('flags')->find($log->id);

    expect($counted->relationLoaded('flags'))->toBeFalse();
    expect($counted->flagged)->toBeTrue();
});

test('the filtered scope can filter by flagged, excluding unflagged logs', function () {
    $flagged = CellStatusLog::factory()->create();
    CellStatusLogFlag::factory()->create(['cell_status_log_id' => $flagged->id]);

    CellStatusLog::factory()->create();

    $request = Request::create('/', 'GET', ['flagged' => true]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($flagged->id);
});

test('the action attribute is cast to a CellLogAction enum', function () {
    $log = CellStatusLog::factory()->create(['action' => CellLogAction::TransferredOut]);

    expect($log->fresh()->action)->toBe(CellLogAction::TransferredOut);
});

test('the from_state and to_state attributes are cast to CellState enums', function () {
    $log = CellStatusLog::factory()->create([
        'from_state' => CellState::Full,
        'to_state' => CellState::Empty,
    ]);

    $fresh = $log->fresh();

    expect($fresh->from_state)->toBe(CellState::Full);
    expect($fresh->to_state)->toBe(CellState::Empty);
});

test('the filtered scope combines multiple filters with AND, not OR', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $matching = CellStatusLog::factory()->create(['product_id' => $product->id, 'user_id' => $user->id]);
    CellStatusLog::factory()->create(['product_id' => $product->id, 'user_id' => $otherUser->id]);
    CellStatusLog::factory()->create(['product_id' => $otherProduct->id, 'user_id' => $user->id]);

    $request = Request::create('/', 'GET', ['product_id' => $product->id, 'user_id' => $user->id]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope can filter by pallet expiration date range, excluding logs outside it', function () {
    $matchingPallet = Pallet::factory()->create(['expiration_date' => '2026-06-15']);
    $outOfRangePallet = Pallet::factory()->create(['expiration_date' => '2026-01-01']);

    $matching = CellStatusLog::factory()->create(['pallet_id' => $matchingPallet->id]);
    CellStatusLog::factory()->create(['pallet_id' => $outOfRangePallet->id]);
    CellStatusLog::factory()->create(['pallet_id' => null]);

    $request = Request::create('/', 'GET', [
        'expiration_date_from' => '2026-06-01',
        'expiration_date_to' => '2026-06-30',
    ]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($matching->id);
});

test('the filtered scope ignores filters that are absent from the request', function () {
    CellStatusLog::factory()->count(3)->create();

    $results = CellStatusLog::query()->filtered(Request::create('/', 'GET'))->get();

    expect($results)->toHaveCount(3);
});

test('the filtered scope can filter by created_within_days, excluding logs older than that window', function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $withinWindow = backdate(CellStatusLog::factory()->create(), '2026-08-10 00:00:00');
    backdate(CellStatusLog::factory()->create(), '2026-08-01 00:00:00');

    $request = Request::create('/', 'GET', ['created_within_days' => 7]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($withinWindow->id);

    Carbon::setTestNow();
});

test('the filtered scope can filter by multiple actions at once, excluding the remaining action', function () {
    $stored = CellStatusLog::factory()->create(['action' => CellLogAction::Stored]);
    $opened = CellStatusLog::factory()->create(['action' => CellLogAction::Opened]);
    CellStatusLog::factory()->create(['action' => CellLogAction::Emptied]);

    $request = Request::create('/', 'GET', ['action' => ['stored', 'opened']]);

    $results = CellStatusLog::query()->filtered($request)->get();

    expect($results->pluck('id')->sort()->values()->all())->toEqual(
        collect([$stored->id, $opened->id])->sort()->values()->all()
    );
});

test('the sorted scope defaults to created_at descending when no sort params are given', function () {
    // Created in the reverse order of their timestamps (lower id gets the later
    // timestamp), so the assertion only passes if the scope truly orders by
    // created_at and not by insertion/id order.
    $newer = backdate(CellStatusLog::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');

    $results = CellStatusLog::query()->sorted(Request::create('/', 'GET'))->get();

    expect($results->pluck('id')->all())->toEqual([$newer->id, $older->id]);
});

test('the sorted scope can sort by created_at ascending', function () {
    // Same reversed id/timestamp setup as the descending-default test above.
    $newer = backdate(CellStatusLog::factory()->create(), '2026-08-01 12:00:00');
    $older = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');

    $request = Request::create('/', 'GET', ['sort_by' => 'created_at', 'sort_direction' => 'asc']);

    $results = CellStatusLog::query()->sorted($request)->get();

    expect($results->pluck('id')->all())->toEqual([$older->id, $newer->id]);
});

test('the sorted scope breaks ties on id, in the requested direction, when created_at values are equal', function () {
    $first = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');
    $second = backdate(CellStatusLog::factory()->create(), '2026-08-01 10:00:00');

    $desc = CellStatusLog::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'desc']))->get();
    expect($desc->pluck('id')->all())->toEqual([$second->id, $first->id]);

    $asc = CellStatusLog::query()->sorted(Request::create('/', 'GET', ['sort_direction' => 'asc']))->get();
    expect($asc->pluck('id')->all())->toEqual([$first->id, $second->id]);
});

test('the sorted scope can sort by the related pallet expiration date, including logs with no pallet', function () {
    $soonPallet = Pallet::factory()->create(['expiration_date' => '2026-06-01']);
    $latePallet = Pallet::factory()->create(['expiration_date' => '2026-12-01']);

    // Created in an order that doesn't match the expected expiration-date order
    // (late, then no-expiration, then soon), so the assertion only passes if the
    // scope truly orders by the pallet's expiration_date and not by insertion/id
    // order.
    $late = CellStatusLog::factory()->create(['pallet_id' => $latePallet->id]);
    $noExpiration = CellStatusLog::factory()->create(['pallet_id' => null]);
    $soon = CellStatusLog::factory()->create(['pallet_id' => $soonPallet->id]);

    $request = Request::create('/', 'GET', ['sort_by' => 'expiration_date', 'sort_direction' => 'asc']);

    $results = CellStatusLog::query()->sorted($request)->get();

    expect($results->pluck('id')->all())->toEqual([$noExpiration->id, $soon->id, $late->id]);
});

test('attachNextLogs sets next_log_at to the next same-pallet log timestamp and computes the seconds between them', function () {
    $pallet = Pallet::factory()->create();

    $first = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Stored]), '2026-08-01 10:00:00');
    $second = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id, 'action' => CellLogAction::Opened]), '2026-08-01 12:00:00');

    $logs = CellStatusLog::query()->orderBy('id')->get();
    CellStatusLog::attachNextLogs($logs);

    $firstFresh = $logs->firstWhere('id', $first->id);
    expect($firstFresh->next_log_at?->equalTo($second->created_at))->toBeTrue();
    expect($firstFresh->duration_seconds)->toBe(7200);
});

test('attachNextLogs leaves next_log_at null and uses the duration until now for the newest log in a pallet chain', function () {
    Carbon::setTestNow('2026-08-01 15:00:00');

    $pallet = Pallet::factory()->create();
    $log = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 10:00:00');

    $logs = CellStatusLog::query()->get();
    CellStatusLog::attachNextLogs($logs);

    $fresh = $logs->firstWhere('id', $log->id);
    expect($fresh->next_log_at)->toBeNull();
    expect($fresh->duration_seconds)->toBe(18000);

    Carbon::setTestNow();
});

test('attachNextLogs skips the paired transferred-in log for a transferred-out entry, using whatever follows it instead', function () {
    $pallet = Pallet::factory()->create();
    $sourceCell = Cell::factory()->create();
    $destinationCell = Cell::factory()->create();

    [$transferredOut, $transferredIn] = createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-01 10:00:00', '2026-08-01 10:00:01');

    $emptied = backdate(CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $destinationCell->id,
        'action' => CellLogAction::Emptied,
    ]), '2026-08-01 14:00:01');

    $logs = CellStatusLog::query()->orderBy('id')->get();
    CellStatusLog::attachNextLogs($logs);

    $transferredOutFresh = $logs->firstWhere('id', $transferredOut->id);
    expect($transferredOutFresh->next_log_at?->equalTo($emptied->created_at))->toBeTrue();
    expect($transferredOutFresh->duration_seconds)->toBe(14401);

    $transferredInFresh = $logs->firstWhere('id', $transferredIn->id);
    expect($transferredInFresh->next_log_at?->equalTo($emptied->created_at))->toBeTrue();
});

test('attachNextLogs leaves next_log_at null and uses the duration until now for a transferred-out log when nothing follows the paired transfer-in', function () {
    Carbon::setTestNow('2026-08-01 16:00:00');

    $pallet = Pallet::factory()->create();
    $sourceCell = Cell::factory()->create();
    $destinationCell = Cell::factory()->create();

    [$transferredOut] = createTransferPair($pallet, $sourceCell, $destinationCell, '2026-08-01 10:00:00', '2026-08-01 10:00:01');

    $logs = CellStatusLog::query()->orderBy('id')->get();
    CellStatusLog::attachNextLogs($logs);

    $transferredOutFresh = $logs->firstWhere('id', $transferredOut->id);
    expect($transferredOutFresh->next_log_at)->toBeNull();
    expect($transferredOutFresh->duration_seconds)->toBe(21600);

    Carbon::setTestNow();
});

test('attachNextLogs only considers logs sharing the same pallet, ignoring a closer-in-time log for another pallet', function () {
    $pallet = Pallet::factory()->create();
    $otherPallet = Pallet::factory()->create();

    $log = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 10:00:00');
    backdate(CellStatusLog::factory()->create(['pallet_id' => $otherPallet->id]), '2026-08-01 10:05:00');
    $actualNext = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 11:00:00');

    $logs = CellStatusLog::query()->orderBy('id')->get();
    CellStatusLog::attachNextLogs($logs);

    $fresh = $logs->firstWhere('id', $log->id);
    expect($fresh->next_log_at?->equalTo($actualNext->created_at))->toBeTrue();
    expect($fresh->duration_seconds)->toBe(3600);
});

test('attachNextLogs treats a log with no pallet as having no next log and uses the duration until now', function () {
    Carbon::setTestNow('2026-08-01 12:00:00');

    $log = backdate(CellStatusLog::factory()->create(['pallet_id' => null]), '2026-08-01 10:00:00');

    $logs = CellStatusLog::query()->get();
    CellStatusLog::attachNextLogs($logs);

    $fresh = $logs->firstWhere('id', $log->id);
    expect($fresh->next_log_at)->toBeNull();
    expect($fresh->duration_seconds)->toBe(7200);

    Carbon::setTestNow();
});

test('attachNextLogs breaks ties on id when same-pallet siblings share the same created_at timestamp', function () {
    $pallet = Pallet::factory()->create();

    $first = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 10:00:00');
    $second = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 10:00:00');
    $third = backdate(CellStatusLog::factory()->create(['pallet_id' => $pallet->id]), '2026-08-01 11:00:00');

    $logs = CellStatusLog::query()->orderBy('id')->get();
    CellStatusLog::attachNextLogs($logs);

    $firstFresh = $logs->firstWhere('id', $first->id);
    expect($firstFresh->next_log_at?->equalTo($second->created_at))->toBeTrue();
    expect($firstFresh->duration_seconds)->toBe(0);

    $secondFresh = $logs->firstWhere('id', $second->id);
    expect($secondFresh->next_log_at?->equalTo($third->created_at))->toBeTrue();
});
