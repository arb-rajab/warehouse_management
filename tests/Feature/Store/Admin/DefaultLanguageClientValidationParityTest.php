<?php

use App\Http\Requests\Store\Admin\UpdateDefaultLanguageRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The default-language form validates in the browser first, from a copy of
 * UpdateDefaultLanguageRequest's rules: resources/js/lib/defaultLanguageRules.json
 * (.ai/rules/client-side-validation.md). The request's after() check (the
 * language exists and is active) reads the database, so it is server-only by
 * construction; the select lists only active languages.
 */

/** @return array<string, mixed> */
function defaultLanguageClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/defaultLanguageRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('the client rules mirror the server rules, in order', function () {
    $request = UpdateDefaultLanguageRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    expect(defaultLanguageClientRulesFile()['rules'])->toBe(app()->call([$request, 'rules']));
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (defaultLanguageClientRulesFile()['rules'] as $field => $rules) {
        foreach ($rules as $rule) {
            expect($tokens[1])->toContain(explode(':', $rule, 2)[0], "{$field}: {$rule}");
        }
    }
});

test('the browser messages repeat the server wording for the default language field', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['string', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    expect($mirror['attributes']['default_language'] ?? null)->toBe($attributes['default_language'] ?? null)
        ->and($attributes['default_language'] ?? null)->not->toBeNull();
})->with(['en', 'ar']);
