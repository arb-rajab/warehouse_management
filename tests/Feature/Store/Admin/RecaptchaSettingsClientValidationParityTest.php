<?php

use App\Http\Requests\Store\Admin\UpdateRecaptchaSettingsRequest;
use App\Support\StoreRecaptchaSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The reCAPTCHA form validates in the browser first, from a copy of
 * UpdateRecaptchaSettingsRequest's rules: resources/js/lib/recaptchaSettingsRules.json,
 * with the messages in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority. The
 * on/off switch is a select that always posts a value (`settings`,
 * `settings.*`: server-only); the request's after() checks read the saved
 * settings and are server-only by construction.
 */

/** @return array<string, mixed> */
function recaptchaClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/recaptchaSettingsRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, list<mixed>> */
function recaptchaServerRules(): array
{
    $request = UpdateRecaptchaSettingsRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    return app()->call([$request, 'rules']);
}

test('the reCAPTCHA form\'s client rules mirror the server rules, in order', function () {
    $file = recaptchaClientRulesFile();
    $server = recaptchaServerRules();

    expect($file['serverOnly'])->toBe(['settings', 'settings.*'])
        ->and(array_keys($file['rules']))->toEqualCanonicalizing(array_keys(array_diff_key($server, array_flip($file['serverOnly']))));

    foreach (array_diff_key($server, array_flip($file['serverOnly'])) as $field => $rules) {
        expect($file['rules'][$field])->toBe($rules, "{$field}: recaptchaSettingsRules.json differs from the server rules");
    }
});

test('the site key pattern is the one the server rule uses', function () {
    expect(recaptchaClientRulesFile()['rules']['recaptcha_site_key'])->toContain('regex:'.StoreRecaptchaSettings::SITE_KEY_PATTERN);
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (recaptchaClientRulesFile()['rules'] as $field => $rules) {
        foreach ($rules as $rule) {
            expect($tokens[1])->toContain(explode(':', $rule, 2)[0], "{$field}: {$rule}");
        }
    }
});

test('the browser messages repeat the server wording for the reCAPTCHA fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['required_with', 'string', 'boolean', 'regex', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (['recaptcha_site_key', 'recaptcha_secret_key'] as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})")
            ->and($attributes[$field] ?? null)->not->toBeNull();
    }
})->with(['en', 'ar']);

test('a line break is refused in the secret, also at the very end, and a bad site key is refused', function () {
    $rules = recaptchaServerRules();

    expect(validator(['recaptcha_secret_key' => "value\n"], $rules)->errors()->has('recaptcha_secret_key'))->toBeTrue()
        ->and(validator(['recaptcha_site_key' => 'a"b'.str_repeat('c', 20)], $rules)->errors()->has('recaptcha_site_key'))->toBeTrue()
        ->and(validator(['recaptcha_site_key' => str_repeat('a', 40)."\n"], $rules)->errors()->has('recaptcha_site_key'))->toBeTrue();
});
