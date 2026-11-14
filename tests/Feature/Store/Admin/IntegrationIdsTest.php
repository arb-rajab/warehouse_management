<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Support\StoreIntegrationIds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Analytics, Facebook chat and Facebook comments pages edit the public IDs
 * that used to live only in `.env` (StoreIntegrationIds,
 * .ai/rules/admin-env-overrides.md): the saved ID wins, `.env` is the fallback,
 * an empty field deletes its row, and an ID lands in script blocks, so it may
 * hold only the characters real IDs use.
 */

const INTEGRATION_ENV = [
    'services.google_analytics.tracking_id' => 'G-ENVVALUE01',
    'services.facebook.pixel_id' => '1111111111',
    'services.facebook.page_id' => '2222222222',
    'services.facebook.app_id' => '3333333333',
];

beforeEach(function () {
    config(INTEGRATION_ENV);
    Cache::forget('business_settings');
});

/**
 * Per page: [route name, update route, permission, switch, ID field, a good ID, the flash message key].
 *
 * @return array<string, array{string, string, string, string, string, string, string}>
 */
function integrationPages(): array
{
    return [
        'analytics (tracking ID)' => ['google_analytics.index', 'google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'G-AB12CD34EF', 'google_analytics_settings_have_been_updated_successfully'],
        'analytics (pixel ID)' => ['google_analytics.index', 'google_analytics.update', 'analytics_tools_configuration', 'facebook_pixel', 'facebook_pixel_id', '1234567890123', 'google_analytics_settings_have_been_updated_successfully'],
        'pixel alias route' => ['google_analytics.index', 'facebook_pixel.update', 'analytics_tools_configuration', 'facebook_pixel', 'facebook_pixel_id', '1234567890123', 'google_analytics_settings_have_been_updated_successfully'],
        'facebook chat' => ['facebook_chat.index', 'facebook_chat.update', 'facebook_chat', 'facebook_chat', 'facebook_page_id', '109876543210', 'facebook_chat_settings_have_been_updated_successfully'],
        'facebook comments' => ['facebook-comment', 'facebook-comment.update', 'facebook_comment', 'facebook_comment', 'facebook_app_id', '5551234567', 'facebook_comments_settings_have_been_updated_successfully'],
    ];
}

dataset('integration pages', integrationPages());

/** The saved `business_settings` rows of the four IDs, by type. */
function integrationRows(): array
{
    return DB::table('business_settings')->whereIn('type', array_keys(StoreIntegrationIds::FIELDS))->pluck('value', 'type')->all();
}

// saving

test('each page saves its ID and its switch, and flashes its success message', function (string $index, string $update, string $permission, string $toggle, string $field, string $id, string $flash) {
    actingAsStoreUser(createStoreStaff($permission));

    $this->post(route($update), ['settings' => [$toggle => 1], $field => $id])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute($index)
        ->assertInertiaFlash('success', __('store_admin.'.$flash));

    expect(integrationRows())->toBe([$field => $id]);
    $this->assertDatabaseHas('business_settings', ['type' => $toggle, 'value' => '1']);
})->with('integration pages');

test('the saved ID is trimmed and replaces the previous one without adding a row', function () {
    actingAsStoreUser(createStoreStaff('analytics_tools_configuration'));

    foreach (['G-FIRST00001', '  G-SECOND0002  '] as $id) {
        $this->post(route('google_analytics.update'), ['settings' => ['google_analytics' => 1], 'google_analytics_tracking_id' => $id])->assertSessionHasNoErrors();
    }

    expect(integrationRows())->toBe(['google_analytics_tracking_id' => 'G-SECOND0002'])
        ->and(DB::table('business_settings')->where('type', 'google_analytics_tracking_id')->count())->toBe(1);
});

test('an empty ID deletes its row and a post without the field leaves it alone', function () {
    actingAsStoreUser(createStoreStaff('analytics_tools_configuration'));
    storeSetting('google_analytics_tracking_id', 'G-SAVED00001');
    storeSetting('facebook_pixel_id', '9999999999');

    $this->post(route('google_analytics.update'), ['settings' => ['google_analytics' => 1, 'facebook_pixel' => 1]])->assertSessionHasNoErrors();
    expect(integrationRows())->toBe(['google_analytics_tracking_id' => 'G-SAVED00001', 'facebook_pixel_id' => '9999999999']);

    $this->post(route('google_analytics.update'), ['settings' => ['google_analytics' => 1, 'facebook_pixel' => 1], 'google_analytics_tracking_id' => '', 'facebook_pixel_id' => '   '])->assertSessionHasNoErrors();
    expect(integrationRows())->toBe([]);
});

test('saving forgets the cached business settings so the storefront sees the new ID', function () {
    actingAsStoreUser(createStoreStaff('facebook_chat'));
    Cache::put('business_settings', collect(['stale']));

    $this->post(route('facebook_chat.update'), ['settings' => ['facebook_chat' => 1], 'facebook_page_id' => '109876543210'])->assertSessionHasNoErrors();

    expect(Cache::has('business_settings'))->toBeFalse();
});

// precedence and fallback

test('a saved ID wins over .env, and .env applies again once it is cleared', function () {
    expect(StoreIntegrationIds::effective('google_analytics_tracking_id'))->toBe(['value' => 'G-ENVVALUE01', 'source' => 'env']);

    storeSetting('google_analytics_tracking_id', 'G-SAVED00001');
    Cache::forget('business_settings');

    expect(StoreIntegrationIds::effective('google_analytics_tracking_id'))->toBe(['value' => 'G-SAVED00001', 'source' => 'admin'])
        ->and(StoreIntegrationIds::fromEnvironment('google_analytics_tracking_id'))->toBe('G-ENVVALUE01');

    DB::table('business_settings')->where('type', 'google_analytics_tracking_id')->delete();
    Cache::forget('business_settings');

    expect(StoreIntegrationIds::get('google_analytics_tracking_id'))->toBe('G-ENVVALUE01');
});

test('with nothing saved and nothing in .env the ID is null', function () {
    config(array_fill_keys(array_keys(INTEGRATION_ENV), null));

    foreach (array_keys(StoreIntegrationIds::FIELDS) as $field) {
        expect(StoreIntegrationIds::get($field))->toBeNull();
    }
});

test('a saved value that is not a well formed ID reads as not saved', function (string $field, string $bad) {
    storeSetting($field, $bad);
    Cache::forget('business_settings');

    expect(StoreIntegrationIds::stored($field))->toBeNull()
        ->and(StoreIntegrationIds::effective($field)['source'])->toBe('env')
        ->and(StoreIntegrationIds::get($field))->toBe(INTEGRATION_ENV[StoreIntegrationIds::FIELDS[$field]]);
})->with([
    'a script breakout' => ['google_analytics_tracking_id', "G-AB');alert(1);//"],
    'a tag' => ['facebook_pixel_id', '123456</script>'],
    'letters in a numeric ID' => ['facebook_page_id', '12345abc'],
    'a line break' => ['facebook_app_id', "12345678\n"],
]);

// what the pages send

test('each page sends the saved ID and the effective one with its source', function () {
    actingAsStoreUser(createStoreStaff('analytics_tools_configuration'));
    storeSetting('google_analytics_tracking_id', 'G-SAVED00001');

    $this->get(route('google_analytics.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/Analytics')
            ->where('ids.google_analytics', ['name' => 'google_analytics_tracking_id', 'value' => 'G-SAVED00001', 'effective' => ['value' => 'G-SAVED00001', 'source' => 'admin']])
            ->where('ids.facebook_pixel', ['name' => 'facebook_pixel_id', 'value' => null, 'effective' => ['value' => '1111111111', 'source' => 'env']]));
});

test('the chat and comments pages send their own ID', function () {
    actingAsStoreUser(createStoreStaff('facebook_chat', 'facebook_comment'));

    $this->get(route('facebook_chat.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/FacebookChat')
            ->where('ids.facebook_chat', ['name' => 'facebook_page_id', 'value' => null, 'effective' => ['value' => '2222222222', 'source' => 'env']]));
    $this->get(route('facebook-comment'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Settings/FacebookComments')
            ->where('ids.facebook_comment', ['name' => 'facebook_app_id', 'value' => null, 'effective' => ['value' => '3333333333', 'source' => 'env']]));
});

test('the pages pass on only a highlight key they hold', function () {
    actingAsStoreUser(createStoreStaff('analytics_tools_configuration'));

    foreach (['facebook_pixel_id', 'google_analytics_tracking_id', 'google_analytics'] as $key) {
        $this->get(route('google_analytics.index', ['highlight' => $key]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('highlight', $key));
    }

    foreach (['facebook_page_id', 'mail_host', ['facebook_pixel_id']] as $key) {
        $this->get(route('google_analytics.index', ['highlight' => $key]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('highlight', null));
    }
});

// validation

test('an ID with a character real IDs never hold is refused before any write', function (string $update, string $permission, string $toggle, string $field, string $bad) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff($permission));

    $this->postJson(route($update), ['settings' => [$toggle => 1], $field => $bad])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(integrationRows())->toBe([])
        ->and(DB::table('business_settings')->count())->toBe(0);
})->with([
    'single quote in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', "G-AB'12"],
    'a script breakout in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', "G-AB');alert(1);//"],
    'double quote in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'G-AB"12'],
    'angle brackets in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'G-</script>'],
    'a newline in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', "G-AB12CD34\nEF"],
    'a carriage return in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', "G-AB12\rCD34EF"],
    'a space in the tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'G-AB 12'],
    'a tracking ID with no prefix' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'AB12CD34EF'],
    'a too long tracking ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'google_analytics_tracking_id', 'G-'.str_repeat('A', 40)],
    'letters in the pixel ID' => ['google_analytics.update', 'analytics_tools_configuration', 'facebook_pixel', 'facebook_pixel_id', '12345abc'],
    'quotes in the pixel ID' => ['google_analytics.update', 'analytics_tools_configuration', 'facebook_pixel', 'facebook_pixel_id', "123456'"],
    'a pixel ID that is too short' => ['google_analytics.update', 'analytics_tools_configuration', 'facebook_pixel', 'facebook_pixel_id', '1234'],
    'a newline inside the page ID' => ['facebook_chat.update', 'facebook_chat', 'facebook_chat', 'facebook_page_id', "12345\n67890"],
    'a negative app ID' => ['facebook-comment.update', 'facebook_comment', 'facebook_comment', 'facebook_app_id', '-123456'],
]);

test('a trailing line break is trimmed away before validation, so only the clean ID is saved', function () {
    actingAsStoreUser(createStoreStaff('analytics_tools_configuration'));

    $this->post(route('google_analytics.update'), ['settings' => ['google_analytics' => 1], 'google_analytics_tracking_id' => "G-AB12CD34EF\n"])->assertSessionHasNoErrors();

    expect(integrationRows())->toBe(['google_analytics_tracking_id' => 'G-AB12CD34EF']);
});

test('a page refuses another page\'s ID and unknown fields', function (string $update, string $permission, string $toggle, string $foreign) {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    actingAsStoreUser(createStoreStaff($permission));

    $this->postJson(route($update), ['settings' => [$toggle => 1], $foreign => '1234567890'])->assertUnprocessable()->assertJsonValidationErrors('settings');

    expect(DB::table('business_settings')->count())->toBe(0);
})->with([
    'the chat page with the app ID' => ['facebook_chat.update', 'facebook_chat', 'facebook_chat', 'facebook_app_id'],
    'the comments page with the page ID' => ['facebook-comment.update', 'facebook_comment', 'facebook_comment', 'facebook_page_id'],
    'the analytics page with the app ID' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'facebook_app_id'],
    'the analytics page with a mail key' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'mail_host'],
    'the analytics page with a reCAPTCHA key' => ['google_analytics.update', 'analytics_tools_configuration', 'google_analytics', 'recaptcha_site_key'],
]);

// authorization

test('saving refuses callers without the page permission and writes nothing', function (string $index, string $update, string $permission, string $toggle, string $field, string $id, string $flash, string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->post(route($update), ['settings' => [$toggle => 1], $field => $id]), $caller);
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('integration pages')->with('refused store admin callers');

test('another settings permission never grants an ID page or its write', function (string $index, string $update, string $permission, string $toggle, string $field, string $id) {
    actingAsStoreUser(createStoreStaff('smtp_settings', 'features_activation', 'google_recaptcha_configuration'));

    $this->get(route($index))->assertForbidden();
    $this->post(route($update), ['settings' => [$toggle => 1], $field => $id])->assertForbidden();
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('integration pages');

test('WMS authentication alone never grants an ID page or its write', function (string $index, string $update, string $permission, string $toggle, string $field, string $id) {
    actingAsAdmin();

    $this->get(route($index))->assertRedirectToRoute('shop.login');
    $this->post(route($update), ['settings' => [$toggle => 1], $field => $id])->assertRedirectToRoute('shop.login');
    expect(DB::table('business_settings')->count())->toBe(0);
})->with('integration pages');

// the storefront renders the effective IDs

/** The storefront page that extends the shared layout. */
function layoutHtml(): string
{
    return test()->get('/temp/register')->assertOk()->getContent();
}

test('the layout prints the effective tracking and pixel IDs, a saved one over .env', function () {
    storeSetting('google_analytics', 1);
    storeSetting('facebook_pixel', 1);

    $html = layoutHtml();
    expect($html)->toContain('https://www.googletagmanager.com/gtag/js?id=G-ENVVALUE01')
        ->toContain("gtag('config', 'G-ENVVALUE01');")
        ->toContain("fbq('init', '1111111111');")
        ->toContain('https://www.facebook.com/tr?id=1111111111&ev=PageView');

    storeSetting('google_analytics_tracking_id', 'G-SAVED00001');
    storeSetting('facebook_pixel_id', '9999999999');
    Cache::forget('business_settings');

    $html = layoutHtml();
    expect($html)->toContain('gtag/js?id=G-SAVED00001')->toContain("gtag('config', 'G-SAVED00001');")
        ->toContain("fbq('init', '9999999999');")->not->toContain('G-ENVVALUE01')->not->toContain('1111111111');
});

test('the layout prints nothing for a switch that is on while no ID is in effect', function () {
    config(array_fill_keys(array_keys(INTEGRATION_ENV), null));
    storeSetting('google_analytics', 1);
    storeSetting('facebook_pixel', 1);
    storeSetting('facebook_chat', 1);

    $html = layoutHtml();

    expect($html)->not->toContain('googletagmanager.com')->not->toContain('gtag(')
        ->not->toContain('fbq(')->not->toContain('facebook.com/tr')->not->toContain('fb-customerchat');
});

test('the layout prints no tag while its switch is off, whatever the ID', function () {
    storeSetting('google_analytics', 0);
    storeSetting('facebook_pixel', 0);
    storeSetting('google_analytics_tracking_id', 'G-SAVED00001');

    expect(layoutHtml())->not->toContain('googletagmanager.com')->not->toContain('fbq(');
});

test('a malformed saved ID never reaches the page: the .env value is printed, escaped', function () {
    config(['services.google_analytics.tracking_id' => "G-ENV');alert(1);//"]);
    storeSetting('google_analytics', 1);
    storeSetting('google_analytics_tracking_id', "G-AB');alert('saved');//");

    $html = layoutHtml();

    // The saved breakout is ignored; the .env text (the operator's own) is
    // JS-escaped in the script (Js::from) and URL-encoded in the src, never raw.
    expect($html)->not->toContain("alert('saved')")
        ->not->toContain("'G-ENV');alert(1);//'")
        ->toContain("gtag('config', 'G-ENV\\u0027);alert(1);\\/\\/');")
        ->toContain('gtag/js?id=G-ENV%27%29%3Balert%281%29%3B%2F%2F');
});

test('the chat widget uses the effective page ID and the comments SDK the effective app ID', function () {
    storeSetting('facebook_chat', 1);
    storeSetting('facebook_page_id', '109876543210');
    Cache::forget('business_settings');

    expect(layoutHtml())->toContain('page_id="109876543210"')->not->toContain('2222222222');

    DB::table('business_settings')->where('type', 'facebook_page_id')->delete();
    Cache::forget('business_settings');

    expect(layoutHtml())->toContain('page_id="2222222222"');
});

test('the meta tag fb:app_id keeps carrying the effective pixel ID', function () {
    $html = layoutHtml();
    expect($html)->toContain('<meta property="fb:app_id" content="1111111111">');

    storeSetting('facebook_pixel_id', '9999999999');
    Cache::forget('business_settings');

    expect(layoutHtml())->toContain('<meta property="fb:app_id" content="9999999999">');
});
