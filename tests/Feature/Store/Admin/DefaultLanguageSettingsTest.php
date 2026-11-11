<?php

use App\Http\Middleware\AppLanguage;
use App\Models\Language;
use App\Providers\AppServiceProvider;
use App\Support\StoreDefaultLanguage;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The Languages page chooses the default content language that used to live
 * only in `.env` (StoreDefaultLanguage, .ai/rules/admin-env-overrides.md): the
 * saved code wins over DEFAULT_LANGUAGE, an empty choice deletes its row, only
 * an existing, active language can be chosen, and the effective code is laid
 * over config('app.default_language') on web requests and queued jobs only.
 */

beforeEach(function () {
    config(['app.default_language' => 'en']);
    Cache::forget('business_settings');
    Cache::forget(StoreDefaultLanguage::CACHE_KEY);
});

/** Insert a language (`languages.status` 1 or 0) and return it. */
function seedDefaultLanguage(string $code, bool $active = true): Language
{
    return Language::forceCreate(['name' => ['en' => 'English', 'ar' => 'Arabic', 'nl' => 'Dutch'][$code] ?? strtoupper($code), 'code' => $code, 'app_lang_code' => $code, 'rtl' => $code === 'ar' ? 1 : 0, 'status' => $active]);
}

function savedDefaultLanguage(): array
{
    return DB::table('business_settings')->where('type', StoreDefaultLanguage::KEY)->pluck('value')->all();
}

function forgetDefaultLanguageCache(): void
{
    Cache::forget(StoreDefaultLanguage::CACHE_KEY);
    Cache::forget('business_settings');
}

// saving

test('an active language can be chosen, with a success message, and replaces the previous choice', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('ar');

    $this->post(route('languages.default.update'), ['default_language' => 'ar'])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('languages.index')
        ->assertInertiaFlash('success', 'Default language has been updated successfully');
    expect(savedDefaultLanguage())->toBe(['ar']);

    $this->post(route('languages.default.update'), ['default_language' => 'en'])->assertSessionHasNoErrors();
    expect(savedDefaultLanguage())->toBe(['en'])
        ->and(DB::table('business_settings')->where('type', 'default_language')->count())->toBe(1);
});

test('saving applies the choice to the config of that process and forgets the cached settings', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('ar');
    Cache::put('business_settings', collect(['stale']));
    Cache::put(StoreDefaultLanguage::CACHE_KEY, 'en');

    $this->post(route('languages.default.update'), ['default_language' => 'ar'])->assertSessionHasNoErrors();

    expect(config('app.default_language'))->toBe('ar')
        ->and(Cache::has('business_settings'))->toBeFalse()
        ->and(app(StoreDefaultLanguage::class)->stored())->toBe('ar');
});

test('an empty choice deletes the row so DEFAULT_LANGUAGE applies again', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('ar');
    storeSetting('default_language', 'ar');

    $this->post(route('languages.default.update'), ['default_language' => ''])->assertSessionHasNoErrors()
        ->assertInertiaFlash('success', 'Default language has been updated successfully');

    expect(savedDefaultLanguage())->toBe([])
        ->and(app(StoreDefaultLanguage::class)->effective())->toBe(['value' => 'en', 'source' => 'env'])
        ->and(config('app.default_language'))->toBe('en');
});

// refusals

test('a language that does not exist is refused, naming it, and nothing is written', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    storeSetting('default_language', 'en');

    $this->postJson(route('languages.default.update'), ['default_language' => 'xx'])->assertUnprocessable()
        ->assertJsonValidationErrors('default_language')
        ->assertJsonPath('errors.default_language.0', 'The language "xx" does not exist.');

    expect(savedDefaultLanguage())->toBe(['en']);
});

test('an inactive language is refused, naming it, and nothing is written', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('nl', false);

    $this->postJson(route('languages.default.update'), ['default_language' => 'nl'])->assertUnprocessable()
        ->assertJsonPath('errors.default_language.0', 'The language "nl" is switched off. Switch it on first or choose another language.');

    expect(savedDefaultLanguage())->toBe([]);
});

test('the choice is validated before any lookup or write', function (mixed $value) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');

    $this->postJson(route('languages.default.update'), ['default_language' => $value])->assertUnprocessable()->assertJsonValidationErrors('default_language');

    expect(savedDefaultLanguage())->toBe([]);
})->with([
    'too long' => [str_repeat('a', 11)],
    'an array' => [['en']],
    'several codes' => [['en', 'ar']],
]);

test('the code is matched exactly, not by case', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('ar');

    $this->postJson(route('languages.default.update'), ['default_language' => "ar' OR '1'='1"])->assertUnprocessable();

    expect(savedDefaultLanguage())->toBe([]);
});

test('choosing the default language refuses callers without the language permission', function (string $caller) {
    actingAsRefusedStoreCaller($caller);
    seedDefaultLanguage('ar');

    assertStoreCallerRefused($this->post(route('languages.default.update'), ['default_language' => 'ar']), $caller);

    expect(savedDefaultLanguage())->toBe([]);
})->with('refused store admin callers');

// precedence and fallback

test('a saved code wins over DEFAULT_LANGUAGE and .env applies again once it is cleared', function () {
    $default = app(StoreDefaultLanguage::class);

    expect($default->effective())->toBe(['value' => 'en', 'source' => 'env']);

    storeSetting('default_language', 'ar');
    forgetDefaultLanguageCache();

    expect($default->effective())->toBe(['value' => 'ar', 'source' => 'admin'])
        ->and($default->get())->toBe('ar')
        ->and($default->fromEnvironment())->toBe('en')
        ->and(default_language())->toBe('ar');

    DB::table('business_settings')->where('type', 'default_language')->delete();
    forgetDefaultLanguageCache();

    expect($default->get())->toBe('en')->and(default_language())->toBe('en');
});

test('with nothing saved and no DEFAULT_LANGUAGE there is no default', function () {
    config(['app.default_language' => null]);

    expect(app(StoreDefaultLanguage::class)->effective())->toBe(['value' => null, 'source' => 'env']);
});

test('a saved value that is not a language code reads as not saved', function (string $bad) {
    storeSetting('default_language', $bad);
    forgetDefaultLanguageCache();

    expect(app(StoreDefaultLanguage::class)->stored())->toBeNull()
        ->and(app(StoreDefaultLanguage::class)->effective())->toBe(['value' => 'en', 'source' => 'env']);
})->with([
    'a quote' => ["ar'"],
    'a space' => ['a r'],
    'too long' => ['abcdefghijk'],
    'a trailing newline' => ["ar\n"],
]);

// applying it

test('apply lays the effective code over the config and keeps the .env code to fall back to', function () {
    $default = app(StoreDefaultLanguage::class);
    storeSetting('default_language', 'ar');
    forgetDefaultLanguageCache();

    $default->apply();

    expect(config('app.default_language'))->toBe('ar')
        ->and($default->fromEnvironment())->toBe('en')
        ->and($default->effective())->toBe(['value' => 'ar', 'source' => 'admin']);

    DB::table('business_settings')->where('type', 'default_language')->delete();
    forgetDefaultLanguageCache();
    $default->apply();

    expect(config('app.default_language'))->toBe('en');
});

test('the provider applies it before a queued job but not when it boots in the console', function () {
    storeSetting('default_language', 'ar');
    forgetDefaultLanguageCache();

    $provider = new AppServiceProvider(app());
    (new ReflectionMethod($provider, 'applyStoreDefaultLanguage'))->invoke($provider);

    // The tests run in the console, as config:cache and optimize do: nothing is laid over the config.
    expect(app()->runningInConsole())->toBeTrue()
        ->and(config('app.default_language'))->toBe('en');

    event(new JobProcessing('database', Mockery::mock(Job::class)->shouldIgnoreMissing()));

    expect(config('app.default_language'))->toBe('ar');
});

test('consumers of the default language read the effective code', function () {
    storeSetting('default_language', 'ar');
    forgetDefaultLanguageCache();
    app(StoreDefaultLanguage::class)->apply();

    // The helper and the (overlaid) config every other reader uses.
    expect(default_language())->toBe('ar')->and(config('app.default_language'))->toBe('ar');

    // The store API's language negotiation falls back to the default only without an App-Language header.
    $seen = [];
    $handle = function (Request $request) use (&$seen) {
        return (new AppLanguage)->handle($request, function () use (&$seen) {
            $seen[] = app()->getLocale();

            return response('ok');
        });
    };
    $handle(Request::create('/api/v1/store/anything'));
    $explicit = Request::create('/api/v1/store/anything');
    $explicit->headers->set('App-Language', 'en');
    $handle($explicit);

    expect($seen)->toBe(['ar', 'en']);
});

// what the page sends

test('the Languages page sends the default in use, its source and the active choices', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('ar');
    seedDefaultLanguage('nl', false);

    $this->get(route('languages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Languages/Index')
            ->where('defaultLanguage', 'en')
            ->where('defaultLanguageSetting', [
                'saved' => null,
                'effective' => ['value' => 'en', 'source' => 'env'],
                'environment' => 'en',
                'state' => 'ok',
                'options' => [
                    ['code' => 'ar', 'name' => 'Arabic', 'active' => true],
                    ['code' => 'en', 'name' => 'English', 'active' => true],
                ],
            ]));
});

test('a saved language is the default in use, selected, and marks its row as the default', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    $arabic = seedDefaultLanguage('ar');
    storeSetting('default_language', 'ar');

    $this->get(route('languages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultLanguage', 'ar')
            ->where('defaultLanguageSetting.saved', 'ar')
            ->where('defaultLanguageSetting.effective', ['value' => 'ar', 'source' => 'admin'])
            ->where('defaultLanguageSetting.environment', 'en')
            ->where('defaultLanguageSetting.state', 'ok')
            ->where('languages.data', fn ($rows) => collect($rows)->firstWhere('id', $arabic->id)['is_default'] === true
                && collect($rows)->firstWhere('code', 'en')['is_default'] === false));
});

test('a saved language that was switched off stays among the choices, marked, with the state inactive', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    seedDefaultLanguage('nl', false);
    storeSetting('default_language', 'nl');

    $this->get(route('languages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultLanguageSetting.state', 'inactive')
            ->where('defaultLanguageSetting.options', [
                ['code' => 'en', 'name' => 'English', 'active' => true],
                ['code' => 'nl', 'name' => 'Dutch', 'active' => false],
            ]));
});

test('a saved language that no longer exists stays among the choices under its code, with the state missing', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('en');
    storeSetting('default_language', 'xx');

    $this->get(route('languages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultLanguageSetting.state', 'missing')
            ->where('defaultLanguageSetting.options.1', ['code' => 'xx', 'name' => 'xx', 'active' => false]));
});

test('an .env default that is not in the language list has the state missing', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    seedDefaultLanguage('ar');
    config(['app.default_language' => 'zz']);

    $this->get(route('languages.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaultLanguageSetting.state', 'missing')
            ->where('defaultLanguageSetting.effective', ['value' => 'zz', 'source' => 'env']));
});

test('the page passes on only the default_language highlight key', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));

    $this->get(route('languages.index', ['highlight' => 'default_language']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('highlight', 'default_language'));

    foreach (['not_a_setting', ['default_language']] as $key) {
        $this->get(route('languages.index', ['highlight' => $key]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('highlight', null));
    }

    $this->get(route('languages.index'))->assertInertia(fn (Assert $page) => $page->where('highlight', null));
});

// the existing guards follow the effective default

test('the language chosen as default cannot be deleted, renamed away or switched off', function () {
    actingAsStoreUser(createStoreAdmin());
    seedDefaultLanguage('en');
    $arabic = seedDefaultLanguage('ar');
    storeSetting('default_language', 'ar');

    $this->delete(route('languages.destroy', $arabic))->assertForbidden();
    $this->post(route('languages.update-status'), ['id' => $arabic->id, 'status' => 0])->assertForbidden();
    $this->postJson(route('languages.update', $arabic), ['name' => 'Changed', 'code' => 'fr', 'app_lang_code' => 'ar'])->assertUnprocessable()->assertJsonValidationErrors('code');

    expect($arabic->fresh())->not->toBeNull()
        ->and((bool) $arabic->fresh()->status)->toBeTrue()
        ->and($arabic->fresh()->code)->toBe('ar');
});

test('the .env default is no longer protected once another language is chosen', function () {
    actingAsStoreUser(createStoreAdmin());
    config(['app.default_language' => 'nl']);
    seedDefaultLanguage('en');
    seedDefaultLanguage('ar');
    $dutch = seedDefaultLanguage('nl');
    storeSetting('default_language', 'ar');

    $this->delete(route('languages.destroy', $dutch))->assertRedirectToRoute('languages.index');

    $this->assertDatabaseMissing('languages', ['id' => $dutch->id]);
});
