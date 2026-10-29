<?php

use App\Models\Language;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function adminLanguage(string $code = 'ar'): Language
{
    return Language::forceCreate(['name' => $code === 'ar' ? 'Arabic' : strtoupper($code), 'code' => $code, 'app_lang_code' => $code, 'rtl' => $code === 'ar' ? 1 : 0, 'status' => true]);
}

function assertAdminLanguage(Assert $language, Language $record): Assert
{
    return $language->where('id', $record->id)->where('name', $record->name)->where('code', $record->code)
        ->where('app_lang_code', $record->app_lang_code)->where('rtl', (bool) $record->rtl)->where('status', (bool) $record->status)
        ->where('is_default', $record->code === config('app.default_language'))->where('is_english', $record->code === 'en');
}

beforeEach(function () {
    config(['app.default_language' => 'en']);
});

test('the language list can be rendered with no languages', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $this->get(route('languages.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Languages/Index'));
});

test('each translation editor can be rendered with no translation keys', function (string $route) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    $this->get(route($route, $language))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Languages/Translations'));
})->with(['languages.show', 'app-translations.show']);

test('the language list renders full narrow records and the server-managed default', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    $this->get(route('languages.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Languages/Index')->where('defaultLanguage', 'en')->where('filters', ['per_page' => 20])
        ->has('languages.data', 1)->has('languages.data.0', fn (Assert $row) => assertAdminLanguage($row, $language)));
});

test('languages paginate and validate the selected page size', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    foreach (range(1, 23) as $n) {
        adminLanguage('z'.$n);
    }
    $this->get(route('languages.index'))->assertInertia(fn (Assert $page) => $page->has('languages.data', 20)->where('languages.meta.total', 23));
    $this->get(route('languages.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('languages.data', 3));
    $this->get(route('languages.index', ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('languages.data', 10));
    $this->getJson(route('languages.index', ['per_page' => 7]))->assertUnprocessable();
});

test('language create and edit pages render', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    $this->get(route('languages.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Languages/Create'));
    $this->get(route('languages.edit', $language))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Languages/Edit')->has('language', fn (Assert $row) => assertAdminLanguage($row, $language)));
});

test('languages can be created and edited with validation and explicit redirects', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $data = ['name' => 'Arabic', 'code' => 'ar', 'app_lang_code' => 'ar'];
    $this->post(route('languages.store'), $data)->assertRedirectToRoute('languages.index');
    $language = Language::firstOrFail();
    $this->post(route('languages.update', $language), [...$data, 'name' => 'العربية'])->assertRedirectToRoute('languages.index');
    $this->assertDatabaseHas('languages', ['id' => $language->id, 'name' => 'العربية']);
    $this->postJson(route('languages.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson(route('languages.store'), ['name' => str_repeat('a', 101), 'code' => 'invalidlanguage', 'app_lang_code' => 'toolongcode'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'code', 'app_lang_code']);
});

test('English and the default language cannot be renamed or deleted or the default deactivated', function () {
    actingAsStoreUser(createStoreAdmin());
    foreach (['en', 'nl'] as $code) {
        config(['app.default_language' => 'nl']);
        $language = adminLanguage($code);
        $this->postJson(route('languages.update', $language), ['name' => 'Changed', 'code' => 'fr', 'app_lang_code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->delete(route('languages.destroy', $language))->assertForbidden();
        expect($language->fresh()->code)->toBe($code);
    }
    $this->post(route('languages.update-status'), ['id' => $language->id, 'status' => 0])->assertForbidden();
    expect((bool) $language->fresh()->status)->toBeTrue();
});

test('language deletion preserves the query and database translations, resets the selected locale, and forgets only its cache', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage('fr');
    DB::table('translations')->insert(['lang' => 'fr', 'lang_key' => 'hello', 'lang_value' => 'Bonjour']);
    Cache::put('wms-sentinel', 'keep');
    Cache::put('app.languages', ['stale']);
    $this->withSession(['locale' => 'fr'])->delete(route('languages.destroy', [$language, 'page' => 2, 'per_page' => 10]))
        ->assertRedirectToRoute('languages.index', ['page' => 2, 'per_page' => 10])->assertSessionHas('locale', 'en');
    $this->assertDatabaseMissing('languages', ['id' => $language->id]);
    $this->assertDatabaseHas('translations', ['lang' => 'fr', 'lang_value' => 'Bonjour']);
    expect(Cache::has('app.languages'))->toBeFalse()->and(Cache::get('wms-sentinel'))->toBe('keep');
});

test('language active and RTL controls validate input and preserve pagination', function (string $route, string $column) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage('fr');
    $this->post(route($route, ['page' => 2]), ['id' => $language->id, 'status' => 1])->assertRedirectToRoute('languages.index', ['page' => 2]);
    expect((bool) $language->fresh()->{$column})->toBeTrue();
    $this->postJson(route($route), ['id' => $language->id, 'status' => 'bad'])->assertUnprocessable();
    $this->postJson(route($route), ['id' => 999999, 'status' => 0])->assertUnprocessable();
    expect((bool) $language->fresh()->{$column})->toBeTrue();
})->with(['status' => ['languages.update-status', 'status'], 'rtl' => ['languages.update_rtl_status', 'rtl']]);

test('translation pages render full records, escape markup, search English keys, and paginate', function (bool $app) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    $table = $app ? 'app_translations' : 'translations';
    $route = $app ? 'app-translations.show' : 'languages.show';
    $id = DB::table($table)->insertGetId(['lang' => 'en', 'lang_key' => 'hello', 'lang_value' => '<script>unsafe()</script>']);
    DB::table($table)->insert(['lang' => 'ar', 'lang_key' => 'hello', 'lang_value' => 'مرحبا']);
    DB::table($table)->insert(['lang' => 'fr', 'lang_key' => 'foreign', 'lang_value' => 'noise']);
    $this->get(route($route, $language))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Languages/Translations')->where('app', $app)
        ->has('language', fn (Assert $row) => assertAdminLanguage($row, $language))
        ->has('translations.data', 1)->has('translations.data.0', fn (Assert $row) => $row
        ->where('id', $id)->where('key', 'hello')->where('source', '<script>unsafe()</script>')->where('value', 'مرحبا'))
        ->where('filters', ['search' => null, 'per_page' => 50]));
    foreach (range(1, 51) as $n) {
        DB::table($table)->insert(['lang' => 'en', 'lang_key' => 'key_'.$n, 'lang_value' => 'English '.$n]);
    }
    $this->get(route($route, $language))->assertInertia(fn (Assert $page) => $page->has('translations.data', 50)->where('translations.meta.total', 52));
    $this->get(route($route, [$language, 'search' => 'hello', 'per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('translations.data', 1));
    $this->getJson(route($route, [$language, 'per_page' => 7]))->assertUnprocessable();
})->with([false, true]);

test('translation editing persists database values, updates legacy consumers, and rejects unknown keys atomically', function (bool $app) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    if ($app) {
        $language->forceFill(['app_lang_code' => 'ar_SA'])->save();
    }
    $table = $app ? 'app_translations' : 'translations';
    $route = $app ? 'app-translations.store' : 'languages.key_value_store';
    DB::table($table)->insert(['lang' => 'en', 'lang_key' => 'hello', 'lang_value' => 'Hello']);
    DB::table($table)->insert(['lang' => 'fr', 'lang_key' => 'hello', 'lang_value' => 'Bonjour']);
    Cache::put('translations-ar', ['hello' => 'stale']);
    $this->post(route($route, ['page' => 2, 'search' => 'hello']), ['id' => $language->id, 'translations' => [['key' => 'hello', 'value' => 'مرحبا']]])
        ->assertRedirectToRoute($app ? 'app-translations.show' : 'languages.show', ['language' => $language, 'page' => 2, 'search' => 'hello']);
    $this->assertDatabaseHas($table, ['lang' => $app ? 'ar_SA' : 'ar', 'lang_key' => 'hello', 'lang_value' => 'مرحبا']);
    $this->assertDatabaseHas($table, ['lang' => 'fr', 'lang_key' => 'hello', 'lang_value' => 'Bonjour']);
    if (! $app) {
        expect(translate('Hello', 'ar'))->toBe('مرحبا');
    }
    $this->postJson(route($route), ['id' => $language->id, 'translations' => [['key' => 'hello', 'value' => 'mutated'], ['key' => 'injected', 'value' => 'bad']]])->assertUnprocessable();
    $this->assertDatabaseMissing($table, ['lang_value' => 'mutated']);
    $this->postJson(route($route), ['id' => 999999, 'translations' => []])->assertUnprocessable();
    $this->postJson(route($route), ['id' => $language->id, 'translations' => [['key' => 'hello', 'value' => ['bad']]]])->assertUnprocessable();
    if ($app) {
        $this->postJson(route($route), ['id' => $language->id, 'translations' => [['key' => 'hello', 'value' => str_repeat('x', 256)]]])->assertUnprocessable();
    }
})->with([false, true]);

test('English app imports validate the entire file before writing and export uses the mobile code', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $this->post(route('app-translations.import'), ['lang_file' => UploadedFile::fake()->createWithContent('en.json', '{"Hello":"Hello there","Bye":"Goodbye"}')])->assertRedirectToRoute('languages.index');
    expect(DB::table('app_translations')->where('lang', 'en')->pluck('lang_value', 'lang_key')->all())->toBe(['Hello' => 'Hello there', 'Bye' => 'Goodbye']);
    foreach (['not JSON', '["invalid"]', '{"First":"would save","Bad":{}}', '{"TooLong":"'.str_repeat('x', 256).'"}'] as $contents) {
        $this->postJson(route('app-translations.import'), ['lang_file' => UploadedFile::fake()->createWithContent('en.json', $contents)])->assertUnprocessable();
        expect(DB::table('app_translations')->count())->toBe(2);
    }
    $this->postJson(route('app-translations.import'))->assertUnprocessable()->assertJsonValidationErrors('lang_file');
    $language = adminLanguage();
    $language->forceFill(['app_lang_code' => 'ar_SA'])->save();
    DB::table('app_translations')->insert(['lang' => 'ar_SA', 'lang_key' => 'Hello', 'lang_value' => 'مرحبا']);
    DB::table('app_translations')->insert(['lang' => 'ar', 'lang_key' => 'noise', 'lang_value' => 'excluded']);
    $response = $this->get(route('app-translations.export', $language))->assertOk()->assertDownload('app_ar_SA.arb');
    expect(json_decode($response->streamedContent(), true))->toBe(['Hello' => 'مرحبا']);
});

test('mobile catalog imports and downloads preserve client keys independently of general translations', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage('en');
    $catalog = [
        'Cart.item-count' => '{count} items in your cart',
        'Checkout.Total' => 'Total: {amount}',
        'checkout_total' => 'Checkout total',
        '@checkout_total' => 'Client-owned string metadata',
    ];

    $this->post(route('app-translations.import'), [
        'lang_file' => UploadedFile::fake()->createWithContent('app_en.json', json_encode($catalog, JSON_THROW_ON_ERROR)),
    ])->assertRedirectToRoute('languages.index');

    $response = $this->get(route('app-translations.export', $language))
        ->assertOk()->assertDownload('app_en.arb')->assertHeader('Content-Type', 'application/json');

    expect(json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR))->toBe($catalog)
        ->and(DB::table('app_translations')->where('lang', 'en')->pluck('lang_value', 'lang_key')->all())->toBe($catalog)
        ->and(DB::table('translations')->count())->toBe(0);
});

test('an empty mobile catalog keeps the existing empty array download without borrowing another language', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage('sa');
    $language->forceFill(['app_lang_code' => 'ar_SA'])->save();
    DB::table('app_translations')->insert([
        ['lang' => 'en', 'lang_key' => 'Cart.item-count', 'lang_value' => '{count} items'],
        ['lang' => 'ar', 'lang_key' => 'Cart.item-count', 'lang_value' => 'عدد العناصر: {count}'],
        ['lang' => 'sa', 'lang_key' => 'Cart.item-count', 'lang_value' => 'Legacy catalog content'],
    ]);

    $response = $this->get(route('app-translations.export', $language))
        ->assertOk()->assertDownload('app_ar_SA.arb')->assertHeader('Content-Type', 'application/json');

    expect($response->streamedContent())->toBe('[]')
        ->and($language->fresh()->code)->toBe('sa')
        ->and($language->fresh()->app_lang_code)->toBe('ar_SA')
        ->and(DB::table('translations')->count())->toBe(0);
});

test('Arabic mobile catalogs retain their exact regional codes and stored content language identities', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $legacy = adminLanguage('sa');
    $legacy->forceFill(['app_lang_code' => 'ar_SA'])->save();
    $arabic = adminLanguage('ar');
    DB::table('app_translations')->insert([
        ['lang' => 'ar_SA', 'lang_key' => 'Checkout.Total', 'lang_value' => 'الإجمالي الإقليمي: {amount}'],
        ['lang' => 'ar', 'lang_key' => 'Checkout.Total', 'lang_value' => 'الإجمالي: {amount}'],
        ['lang' => 'sa', 'lang_key' => 'Excluded.Key', 'lang_value' => 'Excluded legacy catalog'],
    ]);

    $regional = $this->get(route('app-translations.export', $legacy))->assertOk()->assertDownload('app_ar_SA.arb');
    $standard = $this->get(route('app-translations.export', $arabic))->assertOk()->assertDownload('app_ar.arb');

    expect(json_decode($regional->streamedContent(), true, 512, JSON_THROW_ON_ERROR))->toBe(['Checkout.Total' => 'الإجمالي الإقليمي: {amount}'])
        ->and(json_decode($standard->streamedContent(), true, 512, JSON_THROW_ON_ERROR))->toBe(['Checkout.Total' => 'الإجمالي: {amount}'])
        ->and($legacy->fresh()->code)->toBe('sa')
        ->and($legacy->fresh()->app_lang_code)->toBe('ar_SA')
        ->and($arabic->fresh()->code)->toBe('ar')
        ->and($arabic->fresh()->app_lang_code)->toBe('ar')
        ->and(DB::table('translations')->count())->toBe(0);
});

dataset('language admin actions', [
    ['GET', 'languages.index'], ['GET', 'languages.create'], ['POST', 'languages.store'], ['GET', 'languages.edit'], ['POST', 'languages.update'], ['DELETE', 'languages.destroy'],
    ['GET', 'languages.show'], ['GET', 'app-translations.show'], ['POST', 'languages.update-status'], ['POST', 'languages.update_rtl_status'], ['POST', 'languages.key_value_store'],
    ['POST', 'app-translations.store'], ['POST', 'app-translations.import'], ['GET', 'app-translations.export'],
]);

test('every language administration action refuses unauthorized callers without changing data', function (string $method, string $route, string $caller) {
    $language = adminLanguage();
    DB::table('translations')->insert(['lang' => 'en', 'lang_key' => 'hello', 'lang_value' => 'Original']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->call($method, route($route, $language), ['id' => $language->id, 'name' => 'Mutated', 'code' => 'fr', 'status' => 0, 'translations' => [['key' => 'hello', 'value' => 'Mutated']]]), $caller);
    expect($language->fresh()->name)->toBe('Arabic')->and((bool) $language->fresh()->status)->toBeTrue()->and(DB::table('translations')->value('lang_value'))->toBe('Original')->and(DB::table('app_translations')->count())->toBe(0);
})->with('language admin actions')->with('refused store admin callers');

test('missing bound languages return 404', function (string $method, string $route) {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $this->call($method, route($route, 999999))->assertNotFound();
})->with([['GET', 'languages.edit'], ['POST', 'languages.update'], ['DELETE', 'languages.destroy'], ['GET', 'languages.show'], ['GET', 'app-translations.show'], ['GET', 'app-translations.export']]);

test('WMS authentication alone grants no language administration access', function () {
    actingAsAdmin();
    $this->get(route('languages.index'))->assertRedirectToRoute('shop.login');
    $this->post(route('languages.store'), ['name' => 'Arabic', 'code' => 'ar', 'app_lang_code' => 'ar'])->assertRedirectToRoute('shop.login');
    expect(Language::count())->toBe(0);
});

test('content catalog edits refresh custom labels without overriding file-managed interface text', function () {
    actingAsStoreUser(createStoreStaff('language_setup'));
    $language = adminLanguage();
    DB::table('translations')->insert([
        ['lang' => 'en', 'lang_key' => 'amount', 'lang_value' => 'Amount'],
        ['lang' => 'ar', 'lang_key' => 'amount', 'lang_value' => 'Old amount'],
        ['lang' => 'en', 'lang_key' => 'custom_transfer', 'lang_value' => 'Custom transfer'],
        ['lang' => 'ar', 'lang_key' => 'custom_transfer', 'lang_value' => 'Old transfer'],
    ]);
    Cache::put('translations-ar', ['amount' => 'Old amount', 'custom_transfer' => 'Old transfer']);
    Cache::put('translations-fr', ['custom_transfer' => 'Other content locale']);
    Cache::put('wms-sentinel', 'keep');

    $this->post(route('languages.key_value_store'), [
        'id' => $language->id,
        'translations' => [
            ['key' => 'amount', 'value' => 'DATABASE UI OVERRIDE'],
            ['key' => 'custom_transfer', 'value' => 'تحويل مخصص'],
        ],
    ])->assertRedirectToRoute('languages.show', ['language' => $language]);

    expect(Cache::has('translations-ar'))->toBeFalse()
        ->and(Cache::get('translations-fr'))->toBe(['custom_transfer' => 'Other content locale'])
        ->and(Cache::get('wms-sentinel'))->toBe('keep')
        ->and(translate('Custom transfer', 'ar'))->toBe('تحويل مخصص')
        ->and(config('app.default_language'))->toBe('en');
    startStorefrontTranslationQueryLog();
    expect(__('storefront.common.amount', [], 'ar'))->toBe('المبلغ')
        ->and(__('storefront.common.amount', [], 'en'))->toBe('Amount');
    expectNoStorefrontTranslationQueries();
    $this->assertDatabaseHas('translations', ['lang' => 'ar', 'lang_key' => 'amount', 'lang_value' => 'DATABASE UI OVERRIDE']);
    expect(DB::table('translations')->count())->toBe(4);
});
