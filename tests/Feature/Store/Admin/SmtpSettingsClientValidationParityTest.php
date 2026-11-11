<?php

use App\Http\Requests\Store\Admin\UpdateSmtpSettingsRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The SMTP settings form validates in the browser first, from a copy of
 * UpdateSmtpSettingsRequest's rules: resources/js/lib/smtpSettingsRules.json,
 * with the messages in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree. `email:rfc` is listed
 * as server-only (the engine has no email rule), and so is
 * `current_password:store` (the staff member's own password, checked and
 * throttled on the server); the request's after() checks read the saved
 * settings and are server-only by construction.
 */

/** @return array<string, mixed> */
function smtpClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/smtpSettingsRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, list<string>> */
function smtpServerRules(): array
{
    $request = UpdateSmtpSettingsRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    return app()->call([$request, 'rules']);
}

test('the SMTP form\'s client rules mirror the server rules, in order', function () {
    $file = smtpClientRulesFile();
    $server = smtpServerRules();

    expect(array_keys($file['rules']))->toEqualCanonicalizing(array_keys($server));

    foreach ($server as $field => $rules) {
        $serverOnly = $file['serverOnly'][$field] ?? [];

        expect(array_values(array_diff($rules, $serverOnly)))->toBe($file['rules'][$field], "{$field}: smtpSettingsRules.json differs from the server rules")
            ->and(array_values(array_intersect($rules, $serverOnly)))->toBe($serverOnly, "{$field}: a server-only rule the server no longer has");
    }
});

test('only the from address\'s email:rfc rule and the current password check are left to the server', function () {
    expect(smtpClientRulesFile()['serverOnly'])->toBe([
        'from_address' => ['email:rfc'],
        'current_password' => ['current_password:store'],
    ]);
});

test('the current password is required in the browser too, and checked on the store guard', function () {
    expect(smtpServerRules()['current_password'])->toBe(['required', 'string', 'current_password:store'])
        ->and(smtpClientRulesFile()['rules']['current_password'])->toBe(['required', 'string']);
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (smtpClientRulesFile()['rules'] as $field => $rules) {
        foreach ($rules as $rule) {
            expect(in_array(explode(':', $rule, 2)[0], $tokens[1], true))->toBeTrue("{$field}: {$rule}");
        }
    }
});

test('the browser messages repeat the server wording for the SMTP fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['required', 'required_with', 'string', 'integer', 'boolean', 'in', 'regex', 'min.numeric', 'max.numeric', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(smtpClientRulesFile()['rules']) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})");
    }
})->with(['en', 'ar']);

test('a line break is refused in every single-line field, also at the very end', function (string $field) {
    $validator = validator([$field => "value\n", 'host' => 'mail.example.com', 'port' => 587], smtpServerRules());

    expect($validator->errors()->has($field))->toBeTrue();
})->with(['username', 'password', 'from_address', 'from_name']);
