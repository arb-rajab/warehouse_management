<?php

use App\Models\StoreUser;
use App\Models\Upload;

/**
 * Regression coverage for Upload::user() (merged from the legacy store's own
 * Upload model, see .ai/rules/legacy-albaraka-holland.md section 2).
 * Untestable until the migration adding uploads.user_id landed -- see that
 * migration's own docblock for why the column was missing in the first
 * place.
 */
test('user() resolves the upload\'s own owner, not another upload\'s', function () {
    $owner = StoreUser::create([
        'name' => 'Owner', 'email' => 'owner@example.com', 'user_type' => 'customer', 'phone' => '1111111111',
    ]);
    $noiseOwner = StoreUser::create([
        'name' => 'Noise Owner', 'email' => 'noise-owner@example.com', 'user_type' => 'customer', 'phone' => '2222222222',
    ]);

    $upload = Upload::forceCreate(['file_name' => 'mine.jpg', 'user_id' => $owner->id]);
    Upload::forceCreate(['file_name' => 'theirs.jpg', 'user_id' => $noiseOwner->id]);

    expect($upload->user->id)->toBe($owner->id)
        ->and($upload->user->id)->not->toBe($noiseOwner->id);
});
