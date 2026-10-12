<?php

use App\Models\Upload;
use App\Services\TripService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * A driver's checkpoint photos and the generated CMR went through a fourth copy
 * of the uploader (TripService::uploadFiles) that stored whatever it was given
 * under the extension its content had. It is MultipartUploadService now.
 */
function asDriver(): int
{
    $driver = createStoreUser('driver');
    test()->actingAs($driver, 'store');
    Auth::shouldUse('store');

    return $driver->id;
}

test('a driver\'s photos and CMR are stored by what they are, and a file that is not one is skipped', function () {
    $driverId = asDriver();

    withScratchPublicPath(function (string $publicPath) use ($driverId) {
        $ids = app(TripService::class)->uploadFiles([
            UploadedFile::fake()->createWithContent('front.jpg', tinyPng()),
            UploadedFile::fake()->createWithContent('page.png', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('EDITED-PrintCMR-PDF-NL.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
        ]);

        $stored = storedUploadFiles($publicPath);

        expect($ids)->toHaveCount(2)
            ->and(Upload::pluck('id')->all())->toEqual($ids)
            ->and(Upload::pluck('user_id')->unique()->all())->toEqual([$driverId])
            ->and($stored)->toHaveCount(2)
            ->and(collect($stored)->every(fn (string $name) => preg_match('/^[A-Za-z0-9]{40}\.(png|pdf)$/', $name) === 1))->toBeTrue();
    });
});
