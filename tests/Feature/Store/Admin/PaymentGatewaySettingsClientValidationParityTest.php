<?php

use App\Http\Requests\Store\Admin\UpdatePaymentGatewaySettingsRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The Payment methods form validates in the browser first, from a copy of
 * UpdatePaymentGatewaySettingsRequest's rules:
 * resources/js/lib/paymentGatewaySettingsRules.json, with the messages and
 * field names in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree.
 * `current_password:store` is server-only (checked and throttled there), and
 * the request's after() checks read the saved settings, so they are
 * server-only by construction.
 */

/** @return array<string, mixed> */
function paymentGatewayClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/paymentGatewaySettingsRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, list<string>> */
function paymentGatewayServerRules(): array
{
    $request = UpdatePaymentGatewaySettingsRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    return app()->call([$request, 'rules']);
}

test('the payment gateway form\'s client rules mirror the server rules, in order', function () {
    $file = paymentGatewayClientRulesFile();
    $server = paymentGatewayServerRules();

    expect(array_keys($file['rules']))->toEqualCanonicalizing(array_keys($server));

    foreach ($server as $field => $rules) {
        $serverOnly = $file['serverOnly'][$field] ?? [];

        expect(array_values(array_diff($rules, $serverOnly)))->toBe($file['rules'][$field], "{$field}: paymentGatewaySettingsRules.json differs from the server rules")
            ->and(array_values(array_intersect($rules, $serverOnly)))->toBe($serverOnly, "{$field}: a server-only rule the server no longer has");
    }
});

test('only the current password check is left to the server', function () {
    expect(paymentGatewayClientRulesFile()['serverOnly'])->toBe(['current_password' => ['current_password:store']])
        ->and(paymentGatewayServerRules()['current_password'])->toBe(['required', 'string', 'current_password:store'])
        ->and(paymentGatewayClientRulesFile()['rules']['current_password'])->toBe(['required', 'string']);
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (paymentGatewayClientRulesFile()['rules'] as $field => $rules) {
        foreach ($rules as $rule) {
            expect(in_array(explode(':', $rule, 2)[0], $tokens[1], true))->toBeTrue("{$field}: {$rule}");
        }
    }
});

test('the browser messages and field names repeat the server wording', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Arr::dot(Lang::get('validation.attributes', [], $locale));

    foreach (['required', 'required_with', 'string', 'boolean', 'in', 'regex', 'max.string'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(paymentGatewayClientRulesFile()['rules']) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})");
    }

    foreach (array_keys(paymentGatewayClientRulesFile()['rules']) as $field) {
        if ($field !== 'current_password') {
            expect($attributes)->toHaveKey($field);
        }
    }
})->with(['en', 'ar']);

test('a gateway\'s required fields are required with the rest of its group, and a secret never is in the browser', function () {
    $rules = paymentGatewayServerRules();

    expect($rules['gateways.bkash.app_key'][0])->toBe('required_with:gateways.bkash.app_secret,gateways.bkash.username,gateways.bkash.password,gateways.bkash.currency')
        ->and($rules['gateways.bkash.app_secret'])->toBe(['nullable', 'string', 'max:255', 'regex:/^\S+$/D'])
        ->and($rules['gateways.nagad.merchant_number'])->not->toContain('required_with:gateways.nagad.merchant_id,gateways.nagad.pg_public_key,gateways.nagad.merchant_private_key,gateways.nagad.mode');
});

test('a line break is refused in every single-line credential, also at the very end', function (string $field) {
    $key = "gateways.bkash.{$field}";
    $validator = validator(['gateways' => ['bkash' => [$field => "value\n"]]], [$key => paymentGatewayServerRules()[$key]]);

    expect($validator->errors()->has($key))->toBeTrue();
})->with(['app_key', 'app_secret', 'username', 'password']);
