<?php

use App\Models\Upload;
use App\Services\MultipartUploadService;

test('swf files are not in the allowed kinds', function () {
    $kinds = MultipartUploadService::KINDS;
    expect(isset($kinds['swf']))->toBeFalse();
});

test('xml files are not in the allowed kinds', function () {
    $kinds = MultipartUploadService::KINDS;
    expect(isset($kinds['xml']))->toBeFalse();
});
