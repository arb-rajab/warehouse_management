<?php

use App\Models\Upload;

test('swf files are not in the allowed kinds', function () {
    $kinds = \App\Services\MultipartUploadService::KINDS;
    expect(isset($kinds['swf']))->toBeFalse();
});

test('xml files are not in the allowed kinds', function () {
    $kinds = \App\Services\MultipartUploadService::KINDS;
    expect(isset($kinds['xml']))->toBeFalse();
});
