<?php

use App\Support\ReferenceData;
use Illuminate\Support\Facades\DB;

const ENABLED_COUNTRIES = [
    'Austria', 'Belgium', 'Bulgaria', 'Denmark', 'Finland', 'France', 'Germany', 'Hungary', 'Iceland', 'Italy',
    'Luxembourg', 'Malta', 'Netherlands', 'Norway', 'Poland', 'Portugal', 'Romania', 'Slovakia', 'Spain', 'Sweden',
    'Switzerland',
];

function loadReferenceData(): array
{
    return (new ReferenceData)->load();
}

it('leaves the reference tables empty under the testing environment', function (): void {
    expect(DB::table('countries')->count())->toBe(0)
        ->and(DB::table('states')->count())->toBe(0)
        ->and(DB::table('pages')->count())->toBe(0);
});

// The legacy list names two states twice within one country (Kyunggi-do in South Korea, Sussex in
// the United Kingdom); each is loaded once, so 4,091 legacy rows give 4,089 states.
it('loads the legacy rows into empty tables', function (): void {
    expect(loadReferenceData())->toBe([
        'countries' => 246, 'states' => 4089, 'currencies' => 26, 'languages' => 4,
        'pages' => 7, 'page_translations' => 3, 'business_settings' => 1,
    ]);

    expect(DB::table('countries')->count())->toBe(246)
        ->and(DB::table('states')->count())->toBe(4089)
        ->and(DB::table('states')->whereNotIn('country_id', DB::table('countries')->select('id'))->count())->toBe(0);
});

it('enables exactly the countries the legacy store sold to, and keeps their priority', function (): void {
    loadReferenceData();

    expect(DB::table('countries')->where('status', 1)->orderBy('name')->pluck('name')->all())->toBe(ENABLED_COUNTRIES)
        ->and(DB::table('countries')->where('priority', 1)->orderBy('code')->pluck('code')->all())->toBe(['BE', 'DE', 'FR', 'NL'])
        ->and(DB::table('countries')->where('zone_id', '!=', 0)->count())->toBe(0);
});

it('loads the languages, the active currency and the default currency setting', function (): void {
    loadReferenceData();

    $arabic = DB::table('languages')->where('app_lang_code', 'ar')->first();
    $euro = DB::table('currencies')->where('code', 'EUR')->first();

    expect(DB::table('languages')->pluck('name')->sort()->values()->all())->toBe(['Arabic', 'Dutch', 'English', 'German'])
        ->and((int) $arabic->rtl)->toBe(1)
        ->and(DB::table('languages')->where('rtl', 1)->count())->toBe(1)
        ->and(DB::table('currencies')->where('status', 1)->pluck('code')->all())->toBe(['EUR'])
        ->and(DB::table('business_settings')->where('type', 'system_default_currency')->value('value'))->toBe((string) $euro->id);

    // The storefront formats every price through this helper; it fails without the setting.
    expect(get_system_default_currency()->code)->toBe('EUR');
});

it('loads the cms pages with their text cleaned of Word styles', function (): void {
    loadReferenceData();

    $pages = DB::table('pages')->get()->keyBy('type');

    expect($pages->keys()->sort()->values()->all())->toBe([
        'custom_page', 'home_page', 'privacy_policy_page', 'return_policy_page', 'seller_policy_page',
        'support_policy_page', 'terms_conditions_page',
    ])->and($pages['terms_conditions_page']->slug)->toBe('terms')
        ->and($pages['privacy_policy_page']->slug)->toBe('privacy-policy')
        ->and($pages['custom_page']->slug)->toBe('about-us');

    foreach (['terms_conditions_page', 'privacy_policy_page', 'custom_page'] as $type) {
        expect($pages[$type]->content)->not->toBeEmpty()
            ->not->toContain('<style')->not->toContain('<head')->not->toContain('<meta')->not->toContain('Font Definitions');
    }
    expect($pages['privacy_policy_page']->content)->toContain('info@albarakabv.com')
        ->and(DB::table('page_translations')->count())->toBe(3);

    // The invisible right-to-left embedding mark pasted at the end of the About Us title is gone.
    expect($pages['custom_page']->title)->toBe('About Us');
});

it('is idempotent and never updates, renames or removes an existing row', function (): void {
    loadReferenceData();
    DB::table('countries')->where('code', 'NL')->update(['status' => 0, 'name' => 'Holland']);
    DB::table('pages')->where('type', 'terms_conditions_page')->update(['content' => '<p>Our own terms</p>']);
    DB::table('states')->where('id', 1)->delete();
    DB::table('currencies')->where('code', 'USD')->update(['exchange_rate' => 1.5]);

    $second = loadReferenceData();

    expect(DB::table('countries')->where('code', 'NL')->first())->status->toBe(0)->name->toBe('Holland')
        ->and(DB::table('pages')->where('type', 'terms_conditions_page')->value('content'))->toBe('<p>Our own terms</p>')
        ->and((float) DB::table('currencies')->where('code', 'USD')->value('exchange_rate'))->toBe(1.5)
        // A state somebody removed comes back only because nothing with its name exists any more.
        ->and($second['countries'])->toBe(0)->and($second['currencies'])->toBe(0)->and($second['pages'])->toBe(0)
        ->and($second['states'])->toBe(1)->and($second['business_settings'])->toBe(0);

    expect(loadReferenceData())->each->toBe(0);
});

it('attaches states to a country that already exists under another id', function (): void {
    DB::table('countries')->insert(['id' => 9999, 'code' => 'FR', 'name' => 'France (ours)', 'zone_id' => 0, 'status' => 0, 'priority' => 0]);

    loadReferenceData();

    expect(DB::table('countries')->where('code', 'FR')->count())->toBe(1)
        ->and(DB::table('countries')->where('code', 'FR')->value('name'))->toBe('France (ours)')
        ->and(DB::table('states')->where('country_id', 9999)->count())->toBeGreaterThan(100)
        ->and(DB::table('states')->whereNotIn('country_id', DB::table('countries')->select('id'))->count())->toBe(0);
});

it('does not make a second currency active nor replace an existing default currency', function (): void {
    DB::table('currencies')->insert(['id' => 500, 'name' => 'Our Pound', 'symbol' => 'P', 'exchange_rate' => 1, 'status' => 1, 'code' => 'OWN']);
    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => '500']);

    loadReferenceData();

    expect(DB::table('currencies')->where('status', 1)->pluck('code')->all())->toBe(['OWN'])
        ->and(DB::table('business_settings')->where('type', 'system_default_currency')->count())->toBe(1)
        ->and(DB::table('business_settings')->where('type', 'system_default_currency')->value('value'))->toBe('500');
});

it('keeps an existing policy page and still adds its missing translation', function (): void {
    DB::table('pages')->insert(['id' => 77, 'type' => 'terms_conditions_page', 'title' => 'Ours', 'slug' => 'terms', 'content' => '<p>mine</p>']);

    loadReferenceData();

    expect(DB::table('pages')->where('type', 'terms_conditions_page')->count())->toBe(1)
        ->and(DB::table('pages')->where('type', 'terms_conditions_page')->value('content'))->toBe('<p>mine</p>')
        ->and(DB::table('page_translations')->where('page_id', 77)->where('lang', 'en')->count())->toBe(1);
});

it('is wired into the migration for every environment but testing', function (): void {
    $migration = require database_path('migrations/2026_10_21_100000_load_legacy_reference_data.php');

    $migration->up();
    expect(DB::table('countries')->count())->toBe(0);

    $this->app['env'] = 'local';
    $migration->up();
    expect(DB::table('countries')->count())->toBe(246);

    $migration->down();
    expect(DB::table('countries')->count())->toBe(246);
});

it('fails loudly when a data file is missing', function (): void {
    expect(fn () => (new ReferenceData('/nonexistent'))->load())->toThrow(RuntimeException::class, 'Reference data file missing');
});
