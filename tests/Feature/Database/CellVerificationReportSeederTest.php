<?php

use App\Models\CellVerificationReport;
use App\Models\CellVerificationRound;
use Database\Seeders\CellVerificationReportSeeder;
use Database\Seeders\CellVerificationRoundSeeder;
use Database\Seeders\RowSeeder;

test('early-returns without a verification round to seed reports against', function () {
    (new RowSeeder)->run();
    (new CellVerificationReportSeeder)->run();

    expect(CellVerificationReport::count())->toBe(0);
});

test('early-returns without a cell to seed reports against', function () {
    CellVerificationRound::factory()->create();

    (new CellVerificationReportSeeder)->run();

    expect(CellVerificationReport::count())->toBe(0);
});

test('running the seeder twice does not throw and adds more reports', function () {
    (new RowSeeder)->run();
    (new CellVerificationRoundSeeder)->run();

    (new CellVerificationReportSeeder)->run();
    $firstRunCount = CellVerificationReport::count();
    expect($firstRunCount)->toBeGreaterThan(0);

    (new CellVerificationReportSeeder)->run();

    expect(CellVerificationReport::count())->toBeGreaterThan($firstRunCount);
});
