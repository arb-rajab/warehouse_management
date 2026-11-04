<?php

namespace App\Services;

use App\Enums\ProductFieldSource;
use App\Models\BusinessSetting;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Who owns each Otajer-synced product field: the Otajer sync or the Store
 * admin panel. One global choice per field, never both.
 *
 * The syncable fields and their defaults are `config('product_field_sources.fields')`;
 * the stored choices are `business_settings` rows (`product_source_<field>`,
 * value `otajer` or `admin`). A field with no valid stored choice falls back
 * to its configured default, so an untouched install behaves as it did
 * before the setting existed.
 *
 * - Otajer-owned: products:sync writes the field on feed-origin products
 *   (non-null synced_at) and every store writer is refused or ignored there.
 * - Admin-owned: the sync never writes it, on insert or update, and store
 *   writers may change it on any product.
 *
 * Store-origin products (null synced_at) are never touched by the sync, so
 * the store keeps owning every column on them whatever the choice is
 * (Option A, .ai/rules/shared-database.md). See .ai/rules/product-field-sources.md.
 */
class ProductFieldSourceService
{
    /** business_settings.type is VARCHAR(30): fields are at most 15 characters. */
    public const string KEY_PREFIX = 'product_source_';

    /**
     * Every field the Otajer sync writes, in configuration order.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->registry());
    }

    public function isSyncable(string $field): bool
    {
        return array_key_exists($field, $this->registry());
    }

    public function defaultFor(string $field): ProductFieldSource
    {
        return ProductFieldSource::from((string) $this->entry($field)['default']);
    }

    /**
     * The current owner of every syncable field, read in one query.
     *
     * @return array<string, ProductFieldSource>
     */
    public function sources(): array
    {
        $stored = BusinessSetting::query()
            ->whereIn('type', array_map(self::settingKey(...), $this->fields()))
            ->whereNull('lang')
            ->orderBy('id')
            ->get(['type', 'value'])
            ->unique('type')
            ->pluck('value', 'type');

        $sources = [];
        foreach ($this->fields() as $field) {
            $sources[$field] = ProductFieldSource::tryFrom((string) $stored->get(self::settingKey($field)))
                ?? $this->defaultFor($field);
        }

        return $sources;
    }

    public function sourceFor(string $field): ProductFieldSource
    {
        $this->entry($field);

        return $this->sources()[$field];
    }

    public function isOtajerOwned(string $field): bool
    {
        return $this->sourceFor($field) === ProductFieldSource::Otajer;
    }

    public function isAdminOwned(string $field): bool
    {
        return $this->sourceFor($field) === ProductFieldSource::Admin;
    }

    /** @return list<string> */
    public function otajerOwnedFields(): array
    {
        return $this->fieldsOwnedBy(ProductFieldSource::Otajer);
    }

    /** @return list<string> */
    public function adminOwnedFields(): array
    {
        return $this->fieldsOwnedBy(ProductFieldSource::Admin);
    }

    /**
     * What the sync writes into an admin-owned column of a product it creates.
     */
    public function insertValueFor(string $field): string|int
    {
        $value = $this->entry($field)['insert_value'];

        return is_int($value) ? $value : (string) $value;
    }

    /**
     * The syncable fields the store may not change on `$product`: every
     * Otajer-owned field while the product is feed-origin, none otherwise.
     * The product must have `synced_at` loaded.
     *
     * @return list<string>
     */
    public function readOnlyFieldsFor(Product $product): array
    {
        return $product->getAttribute('synced_at') === null ? [] : $this->otajerOwnedFields();
    }

    /**
     * An Otajer writer's update values without the admin-owned fields, so the
     * import never overwrites what the admin set.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function withoutAdminOwned(array $values): array
    {
        return array_diff_key($values, array_flip($this->adminOwnedFields()));
    }

    /**
     * An Otajer writer's insert values with each admin-owned field replaced by
     * its configured insert value, so the feed's value never lands there.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function withInsertValues(array $values): array
    {
        foreach ($this->adminOwnedFields() as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $this->insertValueFor($field);
            }
        }

        return $values;
    }

    /**
     * An Otajer writer's product translation values. The translation's `name`
     * mirrors `$field` (`name` for English, `ar_name` for Arabic) and its
     * `description` mirrors the `description` field; the English translation's
     * `unit` mirrors the `unit` field (the Arabic unit has no field). While
     * the admin owns the mirrored field the import leaves an existing
     * translation's value alone and gives a new one the field's insert value.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function translationValues(string $field, array $values, bool $creating): array
    {
        $mirrored = ['name' => $field, 'description' => 'description'];
        if ($field === 'name') {
            $mirrored['unit'] = 'unit';
        }
        foreach ($mirrored as $key => $source) {
            if (! array_key_exists($key, $values) || ! $this->isAdminOwned($source)) {
                continue;
            }
            if ($creating) {
                $values[$key] = (string) $this->insertValueFor($source);
            } else {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /**
     * Stores the owner of each given field. Every key and value is checked
     * before anything is written; one row per field holds the single choice,
     * and any stray duplicate row for that field is removed so two choices
     * can never apply at once.
     *
     * @param  array<string, ProductFieldSource|string>  $choices
     */
    public function update(array $choices): void
    {
        $resolved = [];
        foreach ($choices as $field => $choice) {
            if (! $this->isSyncable((string) $field)) {
                throw new InvalidArgumentException("[{$field}] is not an Otajer-synced product field.");
            }
            $source = $choice instanceof ProductFieldSource ? $choice : ProductFieldSource::tryFrom($choice);
            if ($source === null) {
                throw new InvalidArgumentException("[{$choice}] is not a product field source.");
            }
            $resolved[(string) $field] = $source;
        }

        DB::transaction(function () use ($resolved): void {
            foreach ($resolved as $field => $source) {
                $key = self::settingKey($field);
                $rows = BusinessSetting::query()->where('type', $key)->orderBy('id')->lockForUpdate()->get();
                $setting = $rows->firstWhere('lang', null) ?? new BusinessSetting;
                BusinessSetting::query()->where('type', $key)->whereKeyNot($setting->getKey() ?? 0)->delete();
                $setting->setAttribute('type', $key);
                $setting->setAttribute('lang', null);
                $setting->setAttribute('value', $source->value);
                $setting->save();
            }
        });
        Cache::forget('business_settings');
    }

    public static function settingKey(string $field): string
    {
        return self::KEY_PREFIX.$field;
    }

    /** @return list<string> */
    private function fieldsOwnedBy(ProductFieldSource $owner): array
    {
        return array_keys(array_filter($this->sources(), fn (ProductFieldSource $source): bool => $source === $owner));
    }

    /** @return array{default: string, insert_value: string|int} */
    private function entry(string $field): array
    {
        if (! $this->isSyncable($field)) {
            throw new InvalidArgumentException("[{$field}] is not an Otajer-synced product field.");
        }

        return $this->registry()[$field];
    }

    /** @return array<string, array{default: string, insert_value: string|int}> */
    private function registry(): array
    {
        /** @var array<string, array{default: string, insert_value: string|int}> */
        return config('product_field_sources.fields', []);
    }
}
