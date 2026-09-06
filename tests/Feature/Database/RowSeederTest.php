<?php

use App\Models\Row;
use Database\Seeders\RowSeeder;

test('running the row seeder twice does not throw and does not create duplicate rows', function () {
    (new RowSeeder)->run();
    (new RowSeeder)->run();

    expect(Row::count())->toBe(4);
    expect(Row::where('letter', 'A')->count())->toBe(1);
    expect(Row::where('letter', 'B')->count())->toBe(1);
    expect(Row::where('letter', 'C')->count())->toBe(1);
    expect(Row::where('letter', 'AA')->count())->toBe(1);
});
