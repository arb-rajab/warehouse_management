<?php

use Illuminate\Support\Facades\Schema;

function loadCreatePersonalAccessTokensTableMigration(): object
{
    return require database_path('migrations/2026_08_08_211531_create_personal_access_tokens_table.php');
}

test('down() drops the wms_ prefixed table', function () {
    loadCreatePersonalAccessTokensTableMigration()->down();

    expect(Schema::hasTable('wms_personal_access_tokens'))->toBeFalse();
});

test('down() falls back to the legacy name once a rename migration has un-prefixed it', function () {
    Schema::rename('wms_personal_access_tokens', 'personal_access_tokens');

    loadCreatePersonalAccessTokensTableMigration()->down();

    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse();
    expect(Schema::hasTable('wms_personal_access_tokens'))->toBeFalse();
});
