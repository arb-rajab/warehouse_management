<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The reference rows the legacy store carried in its own database and a fresh
 * greenfield database does not have: countries and their states, currencies,
 * languages and the CMS pages (the policy pages, the home page and About Us).
 * Without them the registration and address forms have no country to pick,
 * the terms page is a 404 and every price fails, because
 * get_system_default_currency() looks the default currency up by a setting.
 *
 * The rows live in database/data/reference/*.json, extracted from the legacy
 * store's dump (2026-09-12). Page text went through HtmlSanitizer once, with
 * the visible words checked unchanged; the Word-paste styles are gone.
 *
 * Strictly additive and safe to rerun, on an empty database or on one that
 * already carries the legacy rows:
 *  - a row is inserted only when neither its id nor its natural key exists
 *    (country code, currency code, language code, page type, state name within
 *    a country); an existing row is never updated, renamed or deleted;
 *  - states follow their country by code, so they land on the right country even
 *    if its id differs;
 *  - the active currency and the system default currency setting are only
 *    set when none is.
 * Which countries are on sale is the legacy store's own choice: 21 European
 * countries are enabled, the rest are listed but off (the admin Countries page
 * switches them).
 */
final class ReferenceData
{
    private const int CHUNK = 500;

    private string $path;

    /** @var array<int, int> legacy country id => id in this database */
    private array $countryIds = [];

    /** @var array<int, int> legacy page id => id in this database */
    private array $pageIds = [];

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? database_path('data/reference');
    }

    /**
     * Load everything that is missing.
     *
     * @return array<string, int> rows inserted per table
     */
    public function load(): array
    {
        $inserted = [];
        $this->countryIds = [];
        $this->pageIds = [];

        DB::transaction(function () use (&$inserted): void {
            $inserted['countries'] = $this->countries();
            $inserted['states'] = $this->states();
            $inserted['currencies'] = $this->currencies();
            $inserted['languages'] = $this->languages();
            $inserted['pages'] = $this->pages();
            $inserted['page_translations'] = $this->pageTranslations();
            $inserted['business_settings'] = $this->defaultCurrencySetting();
        });

        Cache::forget('business_settings');
        Cache::forget('system_default_currency');

        return $inserted;
    }

    private function countries(): int
    {
        $existing = DB::table('countries')->pluck('id', 'code')->all();
        $taken = array_flip(DB::table('countries')->pluck('id')->all());
        $rows = [];

        foreach ($this->read('countries') as $row) {
            if (isset($existing[$row['code']])) {
                $this->countryIds[$row['id']] = (int) $existing[$row['code']];

                continue;
            }
            $this->countryIds[$row['id']] = (int) $row['id'];
            if (isset($taken[$row['id']])) {
                continue;
            }
            $rows[] = $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('countries', $rows);
    }

    private function states(): int
    {
        $known = [];
        foreach (DB::table('states')->select(['country_id', 'name'])->cursor() as $state) {
            $known[$state->country_id."\0".mb_strtolower($state->name)] = true;
        }
        $taken = array_flip(DB::table('states')->pluck('id')->all());
        $rows = [];

        foreach ($this->read('states') as $row) {
            $countryId = $this->countryIds[$row['country_id']] ?? null;
            $key = $countryId."\0".mb_strtolower($row['name']);
            if ($countryId === null || isset($known[$key]) || isset($taken[$row['id']])) {
                continue;
            }
            $known[$key] = true;
            $rows[] = ['country_id' => $countryId] + $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('states', $rows);
    }

    private function currencies(): int
    {
        $codes = array_flip(DB::table('currencies')->pluck('code')->filter()->all());
        $taken = array_flip(DB::table('currencies')->pluck('id')->all());
        $hasActive = DB::table('currencies')->where('status', 1)->exists();
        $rows = [];

        foreach ($this->read('currencies') as $row) {
            if (isset($codes[$row['code']]) || isset($taken[$row['id']])) {
                continue;
            }
            if ($hasActive) {
                $row['status'] = 0;
            }
            $rows[] = $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('currencies', $rows);
    }

    private function languages(): int
    {
        $codes = array_flip(DB::table('languages')->pluck('code')->all());
        $taken = array_flip(DB::table('languages')->pluck('id')->all());
        $rows = [];

        foreach ($this->read('languages') as $row) {
            if (isset($codes[$row['code']]) || isset($taken[$row['id']])) {
                continue;
            }
            $rows[] = $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('languages', $rows);
    }

    private function pages(): int
    {
        $byType = DB::table('pages')->where('type', '!=', 'custom_page')->pluck('id', 'type')->all();
        $bySlug = DB::table('pages')->where('type', 'custom_page')->pluck('id', 'slug')->all();
        $taken = array_flip(DB::table('pages')->pluck('id')->all());
        $rows = [];

        foreach ($this->read('pages') as $row) {
            $existing = $row['type'] === 'custom_page' ? ($bySlug[$row['slug']] ?? null) : ($byType[$row['type']] ?? null);
            if ($existing !== null) {
                $this->pageIds[$row['id']] = (int) $existing;

                continue;
            }
            $this->pageIds[$row['id']] = (int) $row['id'];
            if (isset($taken[$row['id']])) {
                continue;
            }
            $rows[] = $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('pages', $rows);
    }

    private function pageTranslations(): int
    {
        $known = [];
        foreach (DB::table('page_translations')->select(['page_id', 'lang'])->cursor() as $t) {
            $known[$t->page_id."\0".$t->lang] = true;
        }
        $taken = array_flip(DB::table('page_translations')->pluck('id')->all());
        $rows = [];

        foreach ($this->read('page_translations') as $row) {
            $pageId = $this->pageIds[$row['page_id']] ?? null;
            $key = $pageId."\0".$row['lang'];
            if ($pageId === null || isset($known[$key]) || isset($taken[$row['id']])) {
                continue;
            }
            $known[$key] = true;
            $rows[] = ['page_id' => $pageId] + $row + ['created_at' => now(), 'updated_at' => now()];
        }

        return $this->insert('page_translations', $rows);
    }

    /** Every price goes through get_system_default_currency(); without this setting it fails. */
    private function defaultCurrencySetting(): int
    {
        if (DB::table('business_settings')->where('type', 'system_default_currency')->exists()) {
            return 0;
        }
        $euro = DB::table('currencies')->where('code', 'EUR')->value('id')
            ?? DB::table('currencies')->where('status', 1)->value('id');
        if ($euro === null) {
            return 0;
        }

        return DB::table('business_settings')->insert([
            'type' => 'system_default_currency', 'value' => (string) $euro, 'created_at' => now(), 'updated_at' => now(),
        ]) ? 1 : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(string $name): array
    {
        $file = $this->path.'/'.$name.'.json';
        if (! is_file($file)) {
            throw new RuntimeException("Reference data file missing: {$file}");
        }

        return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): int
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return count($rows);
    }
}
