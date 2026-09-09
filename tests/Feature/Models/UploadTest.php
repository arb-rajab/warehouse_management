<?php

use App\Models\Upload;

test('url returns the external link verbatim when one is set', function () {
    $upload = Upload::factory()->create([
        'external_link' => 'https://cdn.example.com/widget.png',
        'file_name' => 'uploads/all/ignored.png',
    ]);

    // external_link wins even when a file_name is also present: the store uses
    // it for files that already live somewhere else.
    expect($upload->url)->toBe('https://cdn.example.com/widget.png');
});

test('url joins the file name to the store app\'s asset base url', function () {
    config(['store.asset_base_url' => 'https://store.example.com']);
    $upload = Upload::factory()->stored('uploads/all/widget.png')->create();

    expect($upload->url)->toBe('https://store.example.com/uploads/all/widget.png');
});

test('url does not double up slashes between the base url and the file name', function () {
    config(['store.asset_base_url' => 'https://store.example.com/']);
    $upload = Upload::factory()->stored('/uploads/all/widget.png')->create();

    expect($upload->url)->toBe('https://store.example.com/uploads/all/widget.png');
});

test('url is null when the store asset base url is not configured', function () {
    config(['store.asset_base_url' => null]);
    $upload = Upload::factory()->stored('uploads/all/widget.png')->create();

    // A relative path would 404 against this app's own domain, so null is the
    // honest answer.
    expect($upload->url)->toBeNull();
});

test('url is null when the upload has neither a file name nor an external link', function () {
    config(['store.asset_base_url' => 'https://store.example.com']);
    $upload = Upload::factory()->create(['file_name' => null, 'external_link' => null]);

    expect($upload->url)->toBeNull();
});
