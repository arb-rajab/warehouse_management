<?php

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('a used external image is downloaded, stored and the upload points at it', function () {
    Storage::fake();
    Http::fake(['images.test/*' => Http::response(tinyPng())]);

    $upload = Upload::factory()->create(['file_name' => null, 'external_link' => 'https://images.test/a.png']);
    Product::factory()->create(['thumbnail_img' => (string) $upload->id]);

    $this->artisan('fetch:otajer-photos')
        ->expectsOutputToContain('Successfully fetched and stored: https://images.test/a.png')
        ->assertSuccessful();

    $stored = $upload->fresh();

    expect($stored->file_name)->toBe('uploads/otajer/a.png')
        ->and($stored->external_link)->toBeNull();
    Storage::assertExists('uploads/otajer/a.png');
});

test('an upload no product uses is deleted without being downloaded', function () {
    Http::fake();

    $unused = Upload::factory()->create(['file_name' => null, 'external_link' => 'https://images.test/unused.png']);
    $alreadyStored = Upload::factory()->stored('uploads/all/kept.png')->create();

    $this->artisan('fetch:otajer-photos')
        ->expectsOutputToContain("Upload ID {$unused->id} does not exist in any product, record deleted.")
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(Upload::whereKey($unused->id)->exists())->toBeFalse()
        ->and(Upload::whereKey($alreadyStored->id)->exists())->toBeTrue();
});

test('a failed download exits non-zero and leaves the upload for the next run', function () {
    Storage::fake();
    Http::fake(['images.test/*' => Http::response('gone', 404)]);

    $upload = Upload::factory()->create(['file_name' => null, 'external_link' => 'https://images.test/missing.png']);
    Product::factory()->create(['thumbnail_img' => (string) $upload->id]);

    $this->artisan('fetch:otajer-photos')
        ->expectsOutputToContain('Failed to fetch image from: https://images.test/missing.png')
        ->assertFailed();

    expect($upload->fresh()->file_name)->toBeNull()
        ->and($upload->fresh()->external_link)->toBe('https://images.test/missing.png');
});

test('it processes more uploads than one chunk', function () {
    Http::fake();

    $uploads = Upload::factory()->count(105)->create(['file_name' => null]);

    $this->artisan('fetch:otajer-photos')->assertSuccessful();

    expect(Upload::whereIn('id', $uploads->pluck('id'))->count())->toBe(0);
});
