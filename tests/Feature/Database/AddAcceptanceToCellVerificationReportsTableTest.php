<?php

use App\Models\CellVerificationReport;
use App\Models\User;

test('deleting the user who accepted a report nulls the reports accepted_by but keeps accepted_at', function () {
    $acceptedBy = User::factory()->create();
    $report = CellVerificationReport::factory()->accepted()->create(['accepted_by' => $acceptedBy->id]);
    $otherReport = CellVerificationReport::factory()->accepted()->create(); // noise: accepted by someone else

    $acceptedBy->delete();

    expect($report->fresh()->accepted_by)->toBeNull();
    expect($report->fresh()->accepted_at)->not->toBeNull();
    expect($otherReport->fresh()->accepted_by)->not->toBeNull();
});
