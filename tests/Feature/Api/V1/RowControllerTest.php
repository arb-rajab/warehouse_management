<?php

use App\Models\Row;

test('an authenticated worker can list rows with every property the app reads', function () {
    actingAsMobileUser();

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 3, 'flats_count' => 2]);
    $otherRow = Row::factory()->create(['letter' => 'Y', 'cells_count' => 1, 'flats_count' => 1]);

    $response = $this->getJson('/api/v1/rows');

    $response->assertOk();
    expect(collect($response->json('data'))->firstWhere('id', $row->id))->toEqual([
        'id' => $row->id,
        'letter' => 'Z',
        'cells_count' => 3,
        'flats_count' => 2,
        'has_pallets' => false,
    ]);
    expect(collect($response->json('data'))->pluck('letter'))->toContain('Y');
    expect($otherRow->id)->not->toBeNull();
});

test('the row listing paginates instead of returning everything at once', function () {
    actingAsMobileUser();

    Row::factory()->count(25)->create();

    $response = $this->getJson('/api/v1/rows');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('meta.total'))->toBe(25);
});

test('an unauthenticated caller cannot list rows', function () {
    $response = $this->getJson('/api/v1/rows');

    $response->assertUnauthorized();
});
