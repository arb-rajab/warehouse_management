<?php

use App\Http\Controllers\Store\Admin\SettingsController;
use App\Http\Requests\Store\Admin\UpdateIntegrationIdsRequest;
use App\Support\StoreIntegrationIds;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The Analytics, Facebook chat and Facebook comments forms validate in the
 * browser first, from a copy of UpdateIntegrationIdsRequest's rules:
 * resources/js/lib/integrationIdsRules.json, with the messages in
 * resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree. The on/off switches
 * (`settings`, `settings.*`) are selects that always post a value, so they are
 * listed as server-only.
 */

/** @return array<string, mixed> */
function integrationClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/integrationIdsRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** The pages that carry an ID, by section. */
function integrationSections(): array
{
    return array_keys(array_filter(SettingsController::PAGES, fn (array $page): bool => isset($page['ids'])));
}

dataset('integration sections', fn () => integrationSections());

test('the browser copy covers exactly the pages that carry an ID', function () {
    expect(array_keys(integrationClientRulesFile()['sections']))->toEqualCanonicalizing(integrationSections());
});

test('each page\'s client rules mirror the server rules, in order', function (string $section) {
    $file = integrationClientRulesFile();
    $server = UpdateIntegrationIdsRequest::rulesFor($section);
    $checkedByServerOnly = $file['serverOnly'];

    expect(array_keys($file['sections'][$section]))->toEqualCanonicalizing(array_keys(array_diff_key($server, array_flip($checkedByServerOnly))))
        ->and(array_values(array_intersect(array_keys($server), $checkedByServerOnly)))->toBe($checkedByServerOnly);

    foreach (array_diff_key($server, array_flip($checkedByServerOnly)) as $field => $rules) {
        expect($file['sections'][$section][$field])->toBe($rules, "{$section}.{$field}: integrationIdsRules.json differs from the server rules");
    }
})->with('integration sections');

test('every client pattern is the one the server reads a saved ID with', function () {
    $file = integrationClientRulesFile();

    foreach ($file['sections'] as $fields) {
        foreach ($fields as $field => $rules) {
            expect($rules)->toContain('regex:'.StoreIntegrationIds::PATTERNS[$field], "{$field}: pattern");
        }
    }
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (integrationClientRulesFile()['sections'] as $section => $fields) {
        foreach ($fields as $field => $rules) {
            foreach ($rules as $rule) {
                expect($tokens[1])->toContain(explode(':', $rule, 2)[0], "{$section}.{$field}: {$rule}");
            }
        }
    }
});

test('the browser messages repeat the server wording for the ID fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['string', 'regex', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(StoreIntegrationIds::FIELDS) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})")
            ->and($attributes[$field] ?? null)->not->toBeNull();
    }
})->with(['en', 'ar']);

test('a character an ID never holds is refused in every ID field, also at the very end', function (string $section, string $field) {
    foreach (["12345\n", "G-AB'12", 'G-<b>', 'G-AB 12', '12;5678'] as $bad) {
        $validator = validator([$field => $bad], UpdateIntegrationIdsRequest::rulesFor($section));

        expect($validator->errors()->has($field))->toBeTrue("{$field}: ".json_encode($bad));
    }
})->with([
    ['analytics', 'google_analytics_tracking_id'],
    ['analytics', 'facebook_pixel_id'],
    ['facebookChat', 'facebook_page_id'],
    ['facebookComments', 'facebook_app_id'],
]);
