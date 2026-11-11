<?php

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Middleware\BlockMaliciousRequests;
use App\Support\StoreDeliverySettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Order configuration page edits the delivery lead days and timezone that
 * used to live only in `.env` (StoreDeliverySettings,
 * .ai/rules/admin-env-overrides.md): the saved value wins, `.env` is the
 * fallback, an empty field deletes its row, and the order API reads the
 * effective value.
 */

beforeEach(function () {
    config(['store.delivery.lead_days' => 2, 'store.delivery.timezone' => 'Europe/Amsterdam']);
    Cache::forget('business_settings');
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** The saved `business_settings` rows of the delivery settings, by type. */
function deliveryRows(): array
{
    return DB::table('business_settings')->whereIn('type', array_keys(StoreDeliverySettings::FIELDS))->pluck('value', 'type')->all();
}

/** The messages OrderController::deliveryDateErrors() gives for a delivery date. */
function deliveryDateErrorsFor(string $date): array
{
    $method = new ReflectionMethod(OrderController::class, 'deliveryDateErrors');

    return $method->invoke(app(OrderController::class), Request::create('/api/v1/store/order/store', 'POST', ['delivery_date' => $date]));
}

// saving

test('the page saves both settings, flashes its success message and redirects back', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));

    $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => '4', 'delivery_timezone' => 'Europe/London'])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('order_configuration.index')
        ->assertInertiaFlash('success', 'Delivery settings have been updated successfully');

    expect(deliveryRows())->toBe(['delivery_lead_days' => '4', 'delivery_timezone' => 'Europe/London']);
});

test('zero lead days is a valid saved value, not an empty one', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));

    $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => '0', 'delivery_timezone' => ''])->assertSessionHasNoErrors();

    expect(deliveryRows())->toBe(['delivery_lead_days' => '0'])
        ->and(StoreDeliverySettings::effective('delivery_lead_days'))->toBe(['value' => 0, 'source' => 'admin']);
});

test('a new save replaces the previous value without adding a row', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));

    foreach (['3', '6'] as $days) {
        $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => $days])->assertSessionHasNoErrors();
    }

    expect(deliveryRows())->toBe(['delivery_lead_days' => '6'])
        ->and(DB::table('business_settings')->where('type', 'delivery_lead_days')->count())->toBe(1);
});

test('an empty field deletes its row and a post without the field leaves it alone', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    storeSetting('delivery_lead_days', 5);
    storeSetting('delivery_timezone', 'Asia/Dubai');

    $this->post(route('order_configuration.delivery.update'), [])->assertSessionHasNoErrors();
    expect(deliveryRows())->toBe(['delivery_lead_days' => '5', 'delivery_timezone' => 'Asia/Dubai']);

    $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => '', 'delivery_timezone' => '   '])->assertSessionHasNoErrors();
    expect(deliveryRows())->toBe([]);
});

test('saving forgets the cached business settings and leaves the order settings alone', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    storeSetting('minimum_order_amount', '10');
    Cache::put('business_settings', collect(['stale']));

    $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => '1'])->assertSessionHasNoErrors();

    expect(Cache::has('business_settings'))->toBeFalse();
    $this->assertDatabaseHas('business_settings', ['type' => 'minimum_order_amount', 'value' => '10']);
});

// precedence and fallback

test('a saved value wins over .env, and .env applies again once it is cleared', function () {
    expect(StoreDeliverySettings::effective('delivery_lead_days'))->toBe(['value' => 2, 'source' => 'env'])
        ->and(StoreDeliverySettings::effective('delivery_timezone'))->toBe(['value' => 'Europe/Amsterdam', 'source' => 'env']);

    storeSetting('delivery_lead_days', 7);
    storeSetting('delivery_timezone', 'Asia/Tokyo');
    Cache::forget('business_settings');

    expect(StoreDeliverySettings::effective('delivery_lead_days'))->toBe(['value' => 7, 'source' => 'admin'])
        ->and(StoreDeliverySettings::effective('delivery_timezone'))->toBe(['value' => 'Asia/Tokyo', 'source' => 'admin'])
        ->and(StoreDeliverySettings::leadDays())->toBe(7)
        ->and(StoreDeliverySettings::timezone())->toBe('Asia/Tokyo')
        ->and(StoreDeliverySettings::fromEnvironment('delivery_lead_days'))->toBe(2)
        ->and(StoreDeliverySettings::fromEnvironment('delivery_timezone'))->toBe('Europe/Amsterdam');

    DB::table('business_settings')->whereIn('type', array_keys(StoreDeliverySettings::FIELDS))->delete();
    Cache::forget('business_settings');

    expect(StoreDeliverySettings::leadDays())->toBe(2)
        ->and(StoreDeliverySettings::timezone())->toBe('Europe/Amsterdam');
});

test('a saved value that is out of range or not an identifier reads as not saved', function (string $type, string $bad) {
    storeSetting($type, $bad);
    Cache::forget('business_settings');

    expect(StoreDeliverySettings::stored($type))->toBeNull()
        ->and(StoreDeliverySettings::effective($type)['source'])->toBe('env');
})->with([
    'more than 30 days' => ['delivery_lead_days', '31'],
    'a negative number' => ['delivery_lead_days', '-1'],
    'a fraction' => ['delivery_lead_days', '1.5'],
    'text' => ['delivery_lead_days', 'abc'],
    'leading zeros' => ['delivery_lead_days', '007'],
    'an unknown timezone' => ['delivery_timezone', 'Mars/Olympus'],
    'another letter case' => ['delivery_timezone', 'europe/london'],
    'an offset instead of an identifier' => ['delivery_timezone', '+02:00'],
]);

test('the page offers exactly the timezone identifiers PHP knows', function () {
    expect(StoreDeliverySettings::timezones())->toBe(DateTimeZone::listIdentifiers())
        ->and(StoreDeliverySettings::timezones())->toContain('Europe/Amsterdam', 'UTC');
});

// what the page sends

test('the page sends the saved values, the values in use with their source and the .env values', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));
    storeSetting('delivery_lead_days', 3);

    $this->get(route('order_configuration.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Setup/OrderConfiguration/Index')
            ->where('delivery.saved', ['delivery_lead_days' => 3, 'delivery_timezone' => null])
            ->where('delivery.effective', [
                'delivery_lead_days' => ['value' => 3, 'source' => 'admin'],
                'delivery_timezone' => ['value' => 'Europe/Amsterdam', 'source' => 'env'],
            ])
            ->where('delivery.environment', ['delivery_lead_days' => 2, 'delivery_timezone' => 'Europe/Amsterdam'])
            ->where('delivery.bounds', ['min' => 0, 'max' => 30])
            ->where('delivery.timezones', DateTimeZone::listIdentifiers()));
});

test('with nothing saved the page sends empty fields and the .env values as in use', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));

    $this->get(route('order_configuration.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('delivery.saved', ['delivery_lead_days' => null, 'delivery_timezone' => null])
            ->where('delivery.effective.delivery_lead_days', ['value' => 2, 'source' => 'env'])
            ->where('delivery.effective.delivery_timezone', ['value' => 'Europe/Amsterdam', 'source' => 'env']));
});

// validation

test('lead days and the timezone are validated strictly before any write', function (array $input, string $field) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff('order_configuration'));

    $this->postJson(route('order_configuration.delivery.update'), $input)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(deliveryRows())->toBe([])->and(DB::table('business_settings')->count())->toBe(0);
})->with([
    'more than 30 days' => [['delivery_lead_days' => 31], 'delivery_lead_days'],
    'a negative number' => [['delivery_lead_days' => -1], 'delivery_lead_days'],
    'a fraction' => [['delivery_lead_days' => '1.5'], 'delivery_lead_days'],
    'text' => [['delivery_lead_days' => 'two'], 'delivery_lead_days'],
    'an array' => [['delivery_lead_days' => [1]], 'delivery_lead_days'],
    'an unknown timezone' => [['delivery_timezone' => 'Mars/Olympus'], 'delivery_timezone'],
    'another letter case' => [['delivery_timezone' => 'europe/london'], 'delivery_timezone'],
    'an offset' => [['delivery_timezone' => '+02:00'], 'delivery_timezone'],
    'an abbreviation' => [['delivery_timezone' => 'CEST'], 'delivery_timezone'],
    'an array timezone' => [['delivery_timezone' => ['UTC']], 'delivery_timezone'],
]);

test('the boundaries 0 and 30 and a real identifier are accepted', function () {
    actingAsStoreUser(createStoreStaff('order_configuration'));

    foreach ([['0', 'UTC'], ['30', 'America/New_York']] as [$days, $zone]) {
        $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => $days, 'delivery_timezone' => $zone])->assertSessionHasNoErrors();
        expect(deliveryRows())->toBe(['delivery_lead_days' => $days, 'delivery_timezone' => $zone]);
    }
});

// authorization

test('the delivery settings refuse callers without the order configuration permission', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => 9]), $caller);

    expect(deliveryRows())->toBe([]);
})->with('refused store admin callers');

// the consumer

test('the order API reads the lead days saved in the admin', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Amsterdam'));

    // Wednesday. With .env's 2 days the earliest is Friday the 13th.
    expect(deliveryDateErrorsFor('2026-11-13'))->toBe([])
        ->and(deliveryDateErrorsFor('2026-11-12'))->not->toBe([]);

    storeSetting('delivery_lead_days', 5);
    Cache::forget('business_settings');

    // 5 days: the earliest is Monday the 16th.
    expect(deliveryDateErrorsFor('2026-11-13'))->toBe([__('api.order.delivery_date_too_soon', ['date' => '2026-11-16'])])
        ->and(deliveryDateErrorsFor('2026-11-16'))->toBe([]);
});

test('the order API reads the timezone saved in the admin to decide what today is', function () {
    config(['store.delivery.lead_days' => 0]);
    // 23:30 UTC on Wednesday is already Thursday the 12th in Amsterdam, still Wednesday in New York.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 23:30:00', 'UTC'));

    expect(deliveryDateErrorsFor('2026-11-11'))->not->toBe([]);

    storeSetting('delivery_timezone', 'America/New_York');
    Cache::forget('business_settings');

    expect(deliveryDateErrorsFor('2026-11-11'))->toBe([]);
});

test('the order API falls back to .env once the saved values are cleared', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Amsterdam'));
    storeSetting('delivery_lead_days', 5);
    Cache::forget('business_settings');
    expect(deliveryDateErrorsFor('2026-11-13'))->not->toBe([]);

    actingAsStoreUser(createStoreStaff('order_configuration'));
    $this->post(route('order_configuration.delivery.update'), ['delivery_lead_days' => ''])->assertSessionHasNoErrors();

    expect(deliveryDateErrorsFor('2026-11-13'))->toBe([]);
});
