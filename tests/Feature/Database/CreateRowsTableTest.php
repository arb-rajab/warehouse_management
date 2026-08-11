<?php

use App\Models\Row;
use Illuminate\Database\QueryException;

test('a row letter must be unique', function () {
    Row::factory()->create(['letter' => 'Z']);

    expect(fn () => Row::factory()->create(['letter' => 'Z']))->toThrow(QueryException::class);
});
