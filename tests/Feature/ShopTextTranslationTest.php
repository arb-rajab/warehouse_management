<?php

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

/**
 * Shop text comes from the `translations` table through translate(), not from
 * lang/ files through __() (.ai/rules/legacy-albaraka-holland.md section 5).
 */
dataset('shop files', [
    'trip map view' => 'resources/views/shop/backend/trips/create-from-map.blade.php',
    'product details view' => 'resources/views/shop/frontend/product_details.blade.php',
    'trip controller' => 'app/Http/Controllers/Trip/TripController.php',
]);

test('shop files translate through translate(), never __()', function (string $path) {
    expect(file_get_contents(base_path($path)))->not->toMatch('/(?<![\w>:$])__\(/');
})->with('shop files');

test('translate() stores an unseen shop string and returns the DB translation', function () {
    expect(translate('Default starting checkpoint'))->toBe('Default starting checkpoint');

    Translation::where('lang_key', 'default_starting_checkpoint')->where('lang', 'en')->firstOrFail();

    (new Translation)->forceFill(['lang' => 'nl', 'lang_key' => 'default_starting_checkpoint', 'lang_value' => 'Standaard startpunt'])->save();
    Cache::forget('translations-nl');

    expect(translate('Default starting checkpoint', 'nl'))->toBe('Standaard startpunt');
});
