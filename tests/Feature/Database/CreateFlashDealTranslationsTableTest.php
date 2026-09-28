<?php

use Illuminate\Support\Facades\Schema;

test('flash_deal_translations table has the expected columns', function () {
    expect(Schema::hasTable('flash_deal_translations'))->toBeTrue();
    expect(Schema::hasColumns('flash_deal_translations', [
        'id', 'flash_deal_id', 'title', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
