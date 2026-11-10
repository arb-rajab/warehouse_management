<?php

use App\Support\GoogleMapsKey;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['services.google_maps.key' => null]);
    Cache::forget('business_settings');
});

test('the key is null when neither a saved key nor MAP_API_KEY exists', function () {
    expect(GoogleMapsKey::get())->toBeNull()
        ->and(GoogleMapsKey::stored())->toBeNull()
        ->and(GoogleMapsKey::fromEnvironment())->toBeNull();
});

test('MAP_API_KEY is used while no key is saved', function () {
    config(['services.google_maps.key' => 'AIza-env-key']);

    expect(GoogleMapsKey::get())->toBe('AIza-env-key')
        ->and(GoogleMapsKey::stored())->toBeNull();
});

test('the saved key wins over MAP_API_KEY', function () {
    config(['services.google_maps.key' => 'AIza-env-key']);
    storeSetting('google_map_api_key', 'AIza-saved-key');

    expect(GoogleMapsKey::get())->toBe('AIza-saved-key')
        ->and(GoogleMapsKey::stored())->toBe('AIza-saved-key')
        ->and(GoogleMapsKey::fromEnvironment())->toBe('AIza-env-key');
});

test('a blank or whitespace saved key falls back to MAP_API_KEY and a saved key is trimmed', function (?string $stored, ?string $expected) {
    config(['services.google_maps.key' => 'AIza-env-key']);
    storeSetting('google_map_api_key', $stored);

    expect(GoogleMapsKey::get())->toBe($expected);
})->with([
    'null' => [null, 'AIza-env-key'],
    'empty' => ['', 'AIza-env-key'],
    'spaces' => ['   ', 'AIza-env-key'],
    'padded' => [' AIza-saved-key ', 'AIza-saved-key'],
]);

test('resolve applies the same precedence to an already-read value', function () {
    config(['services.google_maps.key' => 'AIza-env-key']);

    expect(GoogleMapsKey::resolve('AIza-saved-key'))->toBe('AIza-saved-key')
        ->and(GoogleMapsKey::resolve(null))->toBe('AIza-env-key')
        ->and(GoogleMapsKey::resolve(' '))->toBe('AIza-env-key');
});

test('the mask shows bullets and the last four characters, or bullets only for a short key', function (?string $key, string $mask) {
    expect(GoogleMapsKey::mask($key))->toBe($mask);
})->with([
    'none' => [null, ''],
    'blank' => ['  ', ''],
    'short' => ['short-key', '••••••••'],
    'eleven characters' => ['12345678901', '••••••••'],
    'twelve characters' => ['123456789012', '••••••••9012'],
    'a real-looking key' => ['AIzaSyTestKey_123-abc', '••••••••-abc'],
]);
