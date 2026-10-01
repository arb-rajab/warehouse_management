<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('brands has the expected columns', function () {
    expect(Schema::hasTable('brands'))->toBeTrue();
    expect(Schema::hasColumns('brands', [
        'id', 'name', 'logo', 'top', 'slug', 'meta_title', 'meta_description',
        'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('brands')->insert([
        'name' => 'Acme',
        'slug' => 'acme',
    ]);

    $this->assertDatabaseHas('brands', [
        'name' => 'Acme',
        'top' => 0,
    ]);
});
