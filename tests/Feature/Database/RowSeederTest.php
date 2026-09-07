<?php

use App\Models\Cell;
use App\Models\Row;
use Database\Seeders\RowSeeder;

test('seeds each row with its documented dimensions and the cells they generate', function () {
    (new RowSeeder)->run();

    $expected = ['A' => [20, 4], 'B' => [15, 3], 'C' => [10, 5], 'AA' => [8, 2]];

    foreach ($expected as $letter => [$cellsCount, $flatsCount]) {
        $row = Row::where('letter', $letter)->sole();

        expect((int) $row->cells_count)->toBe($cellsCount);
        expect((int) $row->flats_count)->toBe($flatsCount);
        expect(Cell::where('row_id', $row->id)->count())->toBe($cellsCount * $flatsCount);
    }
});

test('running the row seeder twice does not throw and does not create duplicate rows', function () {
    (new RowSeeder)->run();
    (new RowSeeder)->run();

    expect(Row::count())->toBe(4);
    expect(Row::where('letter', 'A')->count())->toBe(1);
    expect(Row::where('letter', 'B')->count())->toBe(1);
    expect(Row::where('letter', 'C')->count())->toBe(1);
    expect(Row::where('letter', 'AA')->count())->toBe(1);
});
