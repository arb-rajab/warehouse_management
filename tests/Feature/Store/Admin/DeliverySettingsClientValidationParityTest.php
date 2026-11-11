<?php

use App\Http\Requests\Store\Admin\UpdateDeliverySettingsRequest;
use App\Support\StoreDeliverySettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/*
 * The delivery settings form validates in the browser first, from a copy of
 * UpdateDeliverySettingsRequest's rules: resources/js/lib/deliverySettingsRules.json,
 * with the messages in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree. The timezone's `in`
 * rule (PHP's identifiers) is server-only: the form's select offers the same list.
 */

/** @return array<string, mixed> */
function deliveryClientRulesFile(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/deliverySettingsRules.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return array<string, list<mixed>> */
function deliveryServerRules(): array
{
    $request = UpdateDeliverySettingsRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    return app()->call([$request, 'rules']);
}

test('the client rules mirror the server rules, in order, except the identifier list', function () {
    $file = deliveryClientRulesFile();
    $server = deliveryServerRules();

    expect(array_keys($file['rules']))->toEqualCanonicalizing(array_keys($server));

    foreach ($server as $field => $rules) {
        $client = array_values(array_filter($rules, fn (mixed $rule): bool => ! $rule instanceof In));

        expect($client)->toBe($file['rules'][$field], "{$field}: deliverySettingsRules.json differs from the server rules");
    }
});

test('only the timezone identifier list is left to the server, and it is the whole PHP list', function () {
    $file = deliveryClientRulesFile();
    $inRules = array_values(array_filter(deliveryServerRules()['delivery_timezone'], fn (mixed $rule): bool => $rule instanceof In));

    expect($file['serverOnly'])->toBe(['delivery_timezone' => ['in:timezone-identifiers']])
        ->and($inRules)->toHaveCount(1)
        ->and((string) $inRules[0])->toBe((string) Rule::in(DateTimeZone::listIdentifiers()))
        ->and(deliveryServerRules()['delivery_lead_days'])->toBe(['nullable', 'integer', 'min:'.StoreDeliverySettings::MIN_LEAD_DAYS, 'max:'.StoreDeliverySettings::MAX_LEAD_DAYS]);
});

test('the client bounds are the support class bounds', function () {
    expect(deliveryClientRulesFile()['rules']['delivery_lead_days'])->toContain('min:'.StoreDeliverySettings::MIN_LEAD_DAYS, 'max:'.StoreDeliverySettings::MAX_LEAD_DAYS);
});

test('every client rule token is one the browser engine checks', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/export const supportedTokens = \[(.*?)\] as const;/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1], $tokens);

    foreach (deliveryClientRulesFile()['rules'] as $field => $rules) {
        foreach ($rules as $rule) {
            expect($tokens[1])->toContain(explode(':', $rule, 2)[0], "{$field}: {$rule}");
        }
    }
});

test('the browser messages repeat the server wording for the delivery fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['string', 'integer', 'min.numeric', 'max.numeric'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(deliveryClientRulesFile()['rules']) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})")
            ->and($attributes[$field] ?? null)->not->toBeNull();
    }
})->with(['en', 'ar']);
