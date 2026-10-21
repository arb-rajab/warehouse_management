<?php

use App\Support\RequiredFields\RequiredFieldsAudit;
use App\Support\RequiredFields\ServerRules;

/**
 * The store back office's forms (the Vue pages under resources/js/pages/Store
 * and every form that posts to a Store controller) may not gain a new class
 * A, B or C finding of `php artisan forms:audit-required`
 * (.ai/rules/form-required-fields.md):
 *
 * - A: the server requires a field and its control has no `required`;
 * - B: the server requires a field and its label shows no RequiredMark;
 * - C: a control is required or marked but the server rule is optional.
 *
 * The findings known when the convention was introduced are allowlisted in
 * tests/Feature/Support/required-fields-baseline.json. The list can only
 * shrink: a fixed finding must leave it (the second test fails until it
 * does), and an entry is added only with a `reason` saying why the mismatch
 * is deliberate.
 */
const REQUIRED_FIELDS_BASELINE = __DIR__.'/Support/required-fields-baseline.json';

/**
 * The storefront and seller-panel Blade forms whose server rules, `required`
 * attributes and marks agree (store-backoffice-migration.md §44, §45), by
 * view and the route it posts to. They may not regress to any class A-D
 * finding; there is no baseline for them. Add a form here once it is fixed.
 */
const FIXED_STOREFRONT_FORMS = [
    ['resources/views/shop/auth/register.blade.php', 'route:register'],
    ['resources/views/shop/frontend/user_registration.blade.php', 'route:register'],
    ['resources/views/shop/frontend/partials/address_modal.blade.php', 'route:addresses.store'],
    ['resources/views/shop/frontend/partials/address_edit_modal.blade.php', 'route:addresses.update'],
    ['resources/views/shop/frontend/shipping_info.blade.php', 'route:checkout.store_shipping_infostore'],
    ['resources/views/shop/seller/profile/index.blade.php', 'route:seller.addresses.store'],
    ['resources/views/shop/seller/profile/address_edit_modal.blade.php', 'route:seller.addresses.update'],
    ['resources/views/shop/frontend/seller_form.blade.php', 'route:shops.store'],
    ['resources/views/shop/frontend/user/create_user.blade.php', 'route:store_customer'],
    ['resources/views/shop/auth/passwords/reset.blade.php', 'route:shop.password.update'],
    ['resources/views/shop/frontend/product_details.blade.php', 'route:conversations.store'],
    ['resources/views/shop/frontend/digital_product_details.blade.php', 'route:conversations.store'],
    ['resources/views/shop/frontend/user/support_ticket/index.blade.php', 'route:support_ticket.store'],
];

/**
 * @return array<string, array<string, mixed>> fingerprint => finding
 */
function storeBackOfficeRequiredFieldFindings(): array
{
    static $findings = null;
    if ($findings !== null) {
        return $findings;
    }

    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());
    $forms = array_values(array_filter(
        $audit->vueForms(),
        fn (array $form) => str_starts_with($form['file'], 'resources/js/pages/Store/')
            || str_starts_with($form['target']['controller'] ?? '', 'App\\Http\\Controllers\\Store\\'),
    ));
    $findings = [];
    foreach ($audit->run($forms, [], withInventory: false)['findings'] as $finding) {
        if (in_array($finding['class'], ['A', 'B', 'C'], true)) {
            $findings[$finding['fingerprint']] = $finding;
        }
    }
    ksort($findings);

    return $findings;
}

/**
 * @return array<string, string> fingerprint => reason
 */
function requiredFieldsBaseline(): array
{
    $baseline = json_decode((string) file_get_contents(REQUIRED_FIELDS_BASELINE), true, flags: JSON_THROW_ON_ERROR);

    return collect($baseline['findings'])->mapWithKeys(fn (array $entry) => [$entry['fingerprint'] => $entry['reason']])->all();
}

test('no store back-office form gains a new required-field mismatch (classes A, B, C)', function () {
    $new = array_diff_key(storeBackOfficeRequiredFieldFindings(), requiredFieldsBaseline());

    expect($new)->toBe([], 'New required-field findings. Fix them (see .ai/rules/form-required-fields.md), or, only if the mismatch is deliberate, add them to '
        .'tests/Feature/Support/required-fields-baseline.json with a reason:'."\n"
        .implode("\n", array_map(fn (array $finding) => "  {$finding['fingerprint']}: {$finding['detail']} (UI {$finding['ui']}, server {$finding['server']})", $new)));
});

test('the baseline lists only findings that still occur, so it can only shrink', function () {
    $fixed = array_diff_key(requiredFieldsBaseline(), storeBackOfficeRequiredFieldFindings());

    expect(array_keys($fixed))->toBe([], 'These allowlisted findings are fixed: remove them from tests/Feature/Support/required-fields-baseline.json.');
});

test('every baseline entry says why it may stay, and the file is sorted and unique', function () {
    $entries = json_decode((string) file_get_contents(REQUIRED_FIELDS_BASELINE), true, flags: JSON_THROW_ON_ERROR)['findings'];
    $fingerprints = array_column($entries, 'fingerprint');
    $sorted = $fingerprints;
    sort($sorted);

    expect($fingerprints)->toBe($sorted)
        ->and(array_unique($fingerprints))->toBe($fingerprints)
        ->and(array_filter($entries, fn (array $entry) => trim($entry['reason'] ?? '') === '' || ! preg_match('/^[ABC]\|/', $entry['fingerprint'])))->toBe([]);
});

test('the fixed storefront forms keep their required fields honest (classes A to D)', function () {
    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());
    $files = array_column(FIXED_STOREFRONT_FORMS, 0);
    $result = $audit->run([], array_values(array_filter($audit->bladeForms(), fn (array $form) => in_array($form['file'], $files, true))), withInventory: false);

    $audited = array_map(fn (array $form) => [$form['file'], $form['target']], $result['forms']);
    $findings = array_filter(
        $result['findings'],
        fn (array $finding) => in_array([$finding['form'], $finding['target']], FIXED_STOREFRONT_FORMS, true) && in_array($finding['class'], ['A', 'B', 'C', 'D'], true),
    );

    expect(array_values(array_filter(FIXED_STOREFRONT_FORMS, fn (array $form) => ! in_array($form, $audited, true))))->toBe([], 'A listed form is no longer found: update FIXED_STOREFRONT_FORMS.')
        ->and(array_map(fn (array $finding) => "{$finding['fingerprint']}: {$finding['detail']} (UI {$finding['ui']})", array_values($findings)))->toBe([]);
});
