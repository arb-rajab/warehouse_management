<?php

use App\Models\CellVerificationRound;
use App\Models\Row;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a row cannot be claimed twice by the same round', function () {
    $round = CellVerificationRound::factory()->create();
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    $round->rows()->attach($row->id);

    expect(fn () => $round->rows()->attach($row->id))->toThrow(QueryException::class);
    expect(DB::table('cell_verification_round_row')->count())->toBe(1);
});

test('the same row can be claimed by more than one round', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    // The unique constraint is per round+row: a row moving from one completed
    // round to the next is normal. Refusing two *unfinished* rounds over it is
    // CellVerificationService::startRound()'s job, not the schema's.
    CellVerificationRound::factory()->completed()->covering($row)->create();
    CellVerificationRound::factory()->covering($row)->create();

    expect(DB::table('cell_verification_round_row')->count())->toBe(2);
});

test('deleting a round deletes its row claims, leaving another rounds claims intact', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);

    $round = CellVerificationRound::factory()->covering($row)->create();
    $survivor = CellVerificationRound::factory()->completed()->covering($row)->create(); // noise

    $round->delete();

    expect(DB::table('cell_verification_round_row')->where('cell_verification_round_id', $round->id)->count())->toBe(0);
    expect(DB::table('cell_verification_round_row')->where('cell_verification_round_id', $survivor->id)->count())->toBe(1);
});

test('deleting a row deletes its round claims, leaving another rows claims intact', function () {
    $row = Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 1]);
    $otherRow = Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 1]); // noise

    $round = CellVerificationRound::factory()->covering($row, $otherRow)->create();

    $row->delete();

    expect(DB::table('cell_verification_round_row')->where('row_id', $row->id)->count())->toBe(0);
    expect(DB::table('cell_verification_round_row')->where('row_id', $otherRow->id)->count())->toBe(1);
    expect(CellVerificationRound::find($round->id))->not->toBeNull();
});
