<?php

use App\Http\Requests\Admin\TransferPalletRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/*
 * The admin pallet transfer (the row page's pallet dialog and the warehouse
 * map's transfer dialog) validates in the browser first, from a copy of
 * TransferPalletRequest's rules: resources/js/lib/palletTransferRules.json,
 * with the messages in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority. The
 * `exists` rules read the database, so only the server runs them, as it does
 * the slot lookup in withValidator(). `return_to` and `warehouse_ids` have no
 * control in either dialog, so the browser has no rule for them.
 */

/** Server fields no transfer dialog has a control for. */
const TRANSFER_FIELDS_WITHOUT_CONTROL = ['return_to', 'warehouse_ids', 'warehouse_ids.*'];

/** @return array<string, array<mixed>> */
function transferRequestRules(): array
{
    $request = TransferPalletRequest::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));

    return app()->call([$request, 'rules']);
}

/** @return array<string, list<string>> */
function transferClientRules(): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/palletTransferRules.json')), true, flags: JSON_THROW_ON_ERROR)['rules'];
}

function isServerOnlyTransferRule(mixed $rule): bool
{
    return ! is_string($rule) || str_starts_with($rule, 'exists:');
}

test('the pallet transfer\'s client rules mirror the server rules', function () {
    $server = Arr::except(transferRequestRules(), TRANSFER_FIELDS_WITHOUT_CONTROL);
    $client = transferClientRules();

    expect(array_keys($client))->toEqualCanonicalizing(array_keys($server));

    foreach ($server as $field => $rules) {
        $copied = array_values(array_filter($rules, fn (mixed $rule): bool => ! isServerOnlyTransferRule($rule)));
        $serverOnly = array_values(array_filter($rules, isServerOnlyTransferRule(...)));

        expect($client[$field])->toBe($copied, "{$field}: palletTransferRules.json differs from the server rules");

        foreach ($serverOnly as $rule) {
            expect($rule)->toBeString()->toStartWith('exists:', "{$field}: a server rule neither copied nor listed as server-only");
        }
    }
});

test('only the warehouse and the row letter are checked against the database, which the browser leaves to the server', function () {
    $server = transferRequestRules();
    $exists = array_keys(array_filter($server, fn (array $rules): bool => Arr::first($rules, fn (mixed $rule): bool => is_string($rule) && str_starts_with($rule, 'exists:')) !== null));

    expect($exists)->toEqualCanonicalizing(['warehouse_id', 'row_letter', 'warehouse_ids.*']);
});

test('the fields without a control are the map\'s return target and warehouse filter', function () {
    expect(array_keys(transferRequestRules()))->toContain(...TRANSFER_FIELDS_WITHOUT_CONTROL)
        ->and(array_keys(transferClientRules()))->not->toContain(...TRANSFER_FIELDS_WITHOUT_CONTROL);
});

test('the browser messages repeat the server wording for the transfer fields', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');

    foreach (['required', 'string', 'integer', 'max.string', 'min.numeric'] as $key) {
        expect(Arr::get($mirror['messages'], $key))->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    foreach (array_keys(transferClientRules()) as $field) {
        expect($mirror['attributes'][$field] ?? null)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})");
    }
})->with(['en', 'ar']);
