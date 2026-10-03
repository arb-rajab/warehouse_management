<?php

use App\Models\Upload;

/**
 * Searching the uploader by name used to pass its escape character to where()
 * as the boolean, and a `%` or `_` the user typed matched as a wildcard.
 */
function namedUpload(string $name, int $userId): Upload
{
    return Upload::forceCreate(['file_original_name' => $name, 'file_name' => "uploads/all/$name", 'user_id' => $userId]);
}

test('originalNameContains matches a typed % _ or backslash as itself, not as a wildcard', function (string $term, array $expected) {
    $owner = createStoreUser('customer');

    foreach (['report_final', 'reportXfinal', '100% done', '100 done', 'back\\slash', 'backslash'] as $name) {
        namedUpload($name, $owner->id);
    }

    expect(Upload::originalNameContains($term)->orderBy('id')->pluck('file_original_name')->all())->toBe($expected);
})->with([
    'an underscore' => ['_', ['report_final']],
    'a percent sign' => ['%', ['100% done']],
    'a backslash' => ['\\', ['back\\slash']],
    'a plain word' => ['report', ['report_final', 'reportXfinal']],
    'a word around an underscore' => ['t_f', ['report_final']],
    'a word with no match' => ['missing', []],
]);

test('the uploader search lists only the signed-in account\'s files that match, literally', function () {
    $customer = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    namedUpload('report_final', $customer->id);
    namedUpload('reportXfinal', $customer->id);
    namedUpload('report_stranger', $stranger->id);
    actingAsStoreUser($customer);

    $names = fn (string $search): array => $this->get('/temp/aiz-uploader/get_uploaded_files?'.http_build_query(['search' => $search]))
        ->assertOk()
        ->json('data.*.file_original_name');

    expect($names('_'))->toBe(['report_final'])
        ->and($names('report'))->toEqualCanonicalizing(['report_final', 'reportXfinal'])
        ->and($names('%'))->toBe([]);
});
