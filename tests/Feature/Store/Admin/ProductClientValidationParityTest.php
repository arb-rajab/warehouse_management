<?php

use App\Http\Requests\Store\Admin\StoreDigitalProductRequest;
use App\Http\Requests\Store\Admin\StoreProductRequest;
use App\Http\Requests\Store\Admin\UpdateDigitalProductRequest;
use App\Http\Requests\Store\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;

/*
 * The product forms validate in the browser first, from a copy of the four
 * product FormRequests' rules: resources/js/lib/storeProductRules.json, with
 * the server's closures copied in resources/js/lib/formValidationNamedRules.ts
 * and the messages in resources/js/locales/{en,ar}.json under `validation`
 * (.ai/rules/client-side-validation.md). The server stays the authority; these
 * tests fail as soon as the copy and the server disagree, so a new or changed
 * server rule must be copied (or listed below as server-only) in the same change.
 */

/** The rule names lib/formValidation.ts checks (its `supportedTokens`). */
const CLIENT_RULE_TOKENS = [
    'bail', 'sometimes', 'nullable', 'required', 'required_if', 'required_with', 'string', 'integer',
    'numeric', 'boolean', 'array', 'decimal', 'min', 'max', 'in', 'url', 'regex', 'distinct', 'lt', 'lte', 'gt', 'gte',
];

/**
 * The rules only the server can check, per field, with the reason. A server
 * rule that is neither copied to the client nor listed here fails the parity test.
 *
 * @return array<string, array{list<string>, string}>
 */
function productServerOnlyRules(): array
{
    return [
        'category_id' => [['in'], 'the offered category tree comes from the database; the select offers only those ids'],
        'brand_id' => [['exists'], 'a brand row'],
        'lang' => [['in'], 'the store languages come from the database; the page posts the current one'],
        'slug' => [['closure'], 'the free-slug check reads other products'],
        'photos' => [['closure'], 'upload ownership'],
        'old_photo' => [['closure'], 'upload ownership'],
        'thumbnail_img' => [['exists'], 'upload ownership'],
        'pdf' => [['exists'], 'upload ownership'],
        'meta_img' => [['exists'], 'upload ownership'],
        'file' => [['exists'], 'upload ownership'],
        'variants.*.image' => [['exists'], 'upload ownership'],
        'product_types' => [['list'], 'request shape, not a user mistake'],
        'product_barcodes' => [['list'], 'request shape, not a user mistake'],
        'product_barcodes.*' => [['unique'], 'other products\' barcodes'],
        'colors' => [['list'], 'request shape, not a user mistake'],
        'colors.*' => [['exists'], 'a colour row'],
        'choice_options' => [['list'], 'request shape, not a user mistake'],
        'choice_options.*' => [['array_keys'], 'request shape, not a user mistake'],
        'choice_options.*.attribute_id' => [['exists'], 'an attribute row'],
        'choice_options.*.values' => [['list', 'closure'], 'request shape; the checkboxes cannot repeat a value'],
        'choice_options.*.values.*' => [['closure'], 'the value must exist for that attribute'],
        'variants' => [['required', 'prohibited', 'list', 'size'], 'the row count follows the generated combinations (input-dependent), as do the after() hooks'],
        'variants.*' => [['array_keys'], 'request shape, not a user mistake'],
        'flash_deal_id' => [['exists'], 'an active flash deal row'],
        'tax_id' => [['list'], 'request shape, not a user mistake'],
        'tax_id.*' => [['exists'], 'an active tax row'],
        'tax' => [['list', 'size'], 'request shape; one amount per listed tax'],
        'tax_type' => [['list', 'size'], 'request shape; one type per listed tax'],
    ];
}

/**
 * A rule definition as tokens: `max:200` as written, Rule::in() as
 * `in:a,b`, a closure as `closure`, any other rule object by its snake name.
 *
 * @return list<string>
 */
function serverRuleTokens(mixed $definition): array
{
    if (is_string($definition)) {
        return explode('|', $definition);
    }
    if (is_array($definition)) {
        return array_merge(...array_map(serverRuleTokens(...), array_values($definition)) ?: [[]]);
    }
    if ($definition instanceof In) {
        return ['in:'.implode(',', (fn () => $this->values)->call($definition))];
    }
    if ($definition instanceof Closure) {
        return ['closure'];
    }

    return [Str::snake(class_basename($definition))];
}

/** `max:200` -> `max`; `array:a,b` -> `array_keys` (a shape the client does not check). */
function ruleTokenName(string $token): string
{
    $name = Str::before($token, ':');

    return $name === 'array' && str_contains($token, ':') ? 'array_keys' : $name;
}

/**
 * The request's rules as the form's submit builds them.
 *
 * @param  class-string  $class
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function productRequestRules(string $class, ?Product $product = null, array $input = []): array
{
    $request = $class::create('/', 'POST', $input);
    $request->setContainer(app())->setRedirector(app('redirect'));
    if ($product !== null) {
        $route = (new Route('PUT', 'products/{product}', fn () => null))->bind($request);
        $route->setParameter('product', $product);
        $request->setRouteResolver(fn () => $route);
    }

    return app()->call([$request, 'rules']);
}

/** @return array<string, mixed> */
function productClientSpec(string $kind): array
{
    return json_decode((string) file_get_contents(resource_path('js/lib/storeProductRules.json')), true, flags: JSON_THROW_ON_ERROR)[$kind];
}

/**
 * The client's rules for one form, composed as lib/storeProductForm.ts does.
 *
 * @param  array<string, bool>  $sections
 * @param  list<string>  $readOnly
 * @return array<string, list<string>>
 */
function productClientRules(string $kind, string $form, array $sections, array $readOnly = []): array
{
    $spec = productClientSpec($kind);
    $rules = [];
    foreach ([...$spec['rules'], ...$spec[$form]] as $field => $entry) {
        if (array_is_list($entry)) {
            $rules[$field] = $entry;
        } elseif ($sections[$entry['when']]) {
            $rules[$field] = $entry['rules'];
        }
    }
    foreach ($readOnly as $field) {
        if (in_array($field, $spec['readOnlyNullable'], true)) {
            $rules[$field] = ['nullable'];
        }
    }

    return $rules;
}

/**
 * Every server field (bar the excluded ones) is in the client copy with the
 * same client-checkable rules in the same order; everything else the server
 * checks is a named client rule (a closure) or listed as server-only.
 *
 * @param  array<string, mixed>  $server
 * @param  array<string, list<string>>  $client
 * @param  array<string, list<string>>  $named
 */
function expectClientRulesMirrorServer(array $server, array $client, array $named): void
{
    $fields = array_keys(array_filter($server, fn (mixed $rules): bool => serverRuleTokens($rules) !== ['exclude']));
    expect(array_keys($client))->toEqualCanonicalizing($fields);

    foreach ($fields as $field) {
        $allowed = productServerOnlyRules()[$field][0] ?? [];
        $copied = [];
        $unmirrored = [];
        foreach (serverRuleTokens($server[$field]) as $token) {
            $name = ruleTokenName($token);
            if (in_array($name, $allowed, true) || ($name === 'closure' && isset($named[$field]))) {
                continue;
            }
            if (in_array($name, CLIENT_RULE_TOKENS, true)) {
                $copied[] = $token;
            } else {
                $unmirrored[] = $token;
            }
        }
        expect($unmirrored)->toBe([], "{$field}: server rules neither copied to storeProductRules.json nor listed as server-only");
        expect($client[$field])->toBe($copied, "{$field}: storeProductRules.json differs from the server rules");
    }
    foreach (array_keys($named) as $field) {
        if (isset($server[$field])) {
            expect(serverRuleTokens($server[$field]))->toContain('closure');
        }
    }
}

/** @return array<string, bool> */
function productSections(bool $on): array
{
    foreach (['Setup_Shipping_section', 'Flash_deals', 'VAT_TAX_section', 'cash_payment'] as $type) {
        storeSetting($type, $on ? '1' : '0');
    }
    storeSetting('shipping_type', 'product_wise_shipping');

    return [
        'shipping' => StoreProductRequest::shippingSectionEnabled(),
        'productWiseShipping' => StoreProductRequest::productWiseShippingEnabled(),
        'flashDeals' => StoreProductRequest::flashDealsEnabled(),
        'vat' => StoreProductRequest::vatSectionEnabled(),
        'cashOnDelivery' => StoreProductRequest::cashPaymentEnabled(),
    ];
}

function storeOriginProduct(int $digital): Product
{
    return (new Product)->forceFill(['digital' => $digital]);
}

test('the physical product forms\' client rules mirror the server rules', function (bool $sectionsOn) {
    $sections = productSections($sectionsOn);
    expect($sections)->each->toBe($sectionsOn);
    $named = productClientSpec('physical')['named'];

    expectClientRulesMirrorServer(productRequestRules(StoreProductRequest::class), productClientRules('physical', 'create', $sections), $named);
    expectClientRulesMirrorServer(productRequestRules(UpdateProductRequest::class, storeOriginProduct(0)), productClientRules('physical', 'edit', $sections), $named);
})->with(['every section on' => [true], 'every section off' => [false]]);

test('the digital product forms\' client rules mirror the server rules', function () {
    $named = productClientSpec('digital')['named'];

    expectClientRulesMirrorServer(productRequestRules(StoreDigitalProductRequest::class), productClientRules('digital', 'create', []), $named);
    expectClientRulesMirrorServer(productRequestRules(UpdateDigitalProductRequest::class, storeOriginProduct(1)), productClientRules('digital', 'edit', []), $named);
});

test('the client skips the read-only Otajer fields the server does not validate as posted', function () {
    $sections = productSections(true);
    // A feed-origin row. `synced_at` is set raw: the model refuses the store assigning it, as only the sync stamps it.
    $product = (new Product)->setRawAttributes(['digital' => 0, 'synced_at' => now()->toDateTimeString()]);
    $server = productRequestRules(UpdateProductRequest::class, $product, ['lang' => config('app.default_language')]);
    $swapped = array_keys(array_filter($server, fn (mixed $rules): bool => $rules === ['nullable']));

    expect($swapped)->toEqualCanonicalizing(productClientSpec('physical')['readOnlyNullable']);
    expectClientRulesMirrorServer($server, productClientRules('physical', 'edit', $sections, $swapped), productClientSpec('physical')['named']);
});

test('every server-only entry names a field a product request validates', function () {
    $fields = [];
    foreach (['physical', 'digital'] as $kind) {
        foreach (['rules', 'create', 'edit'] as $part) {
            array_push($fields, ...array_keys(productClientSpec($kind)[$part]));
        }
    }

    expect(array_diff(array_keys(productServerOnlyRules()), $fields))->toBe([])
        ->and(array_filter(productServerOnlyRules(), fn (array $entry): bool => $entry[1] === ''))->toBe([]);
});

test('every named client rule has its copy in the registry', function () {
    $registry = (string) file_get_contents(resource_path('js/lib/formValidationNamedRules.ts'));
    foreach (['physical', 'digital'] as $kind) {
        foreach (productClientSpec($kind)['named'] as $names) {
            foreach ($names as $name) {
                expect(preg_match('/^    '.preg_quote($name, '/').': \{$/m', $registry))->toBe(1, "{$name} is missing from formValidationNamedRules.ts");
            }
        }
    }
});

test('the client checks exactly the rule tokens the parity test treats as copyable', function () {
    $engine = (string) file_get_contents(resource_path('js/lib/formValidation.ts'));
    preg_match('/supportedTokens = \[(.*?)\] as const/s', $engine, $match);
    preg_match_all("/'([a-z_]+)'/", $match[1] ?? '', $tokens);

    expect($tokens[1])->toEqualCanonicalizing(CLIENT_RULE_TOKENS);
});

test('the browser messages repeat the server wording', function (string $locale) {
    $mirror = json_decode((string) file_get_contents(resource_path("js/locales/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR)['validation'];
    foreach (Arr::dot($mirror['messages']) as $key => $line) {
        expect($line)->toBe(Lang::get("validation.{$key}", [], $locale), "validation.{$key} ({$locale})");
    }

    $attributes = Lang::get('validation.attributes', [], $locale) + Lang::get('validation.attributes', [], 'en');
    foreach ($mirror['attributes'] as $field => $line) {
        expect($line)->toBe($attributes[$field] ?? null, "validation.attributes.{$field} ({$locale})");
    }
    foreach (['physical', 'digital'] as $kind) {
        foreach (['rules', 'create', 'edit'] as $part) {
            foreach (array_keys(productClientSpec($kind)[$part]) as $field) {
                if (isset($attributes[$field])) {
                    expect($mirror['attributes'])->toHaveKey($field);
                }
            }
        }
    }
})->with(['en', 'ar']);
