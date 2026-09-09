<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * A row in the store app's shared `products` table.
 *
 * Read-only here — the store owns every write. `image_url` and `boxes_count`
 * are not columns on it: the first resolves through the store's `uploads`
 * table, the second through this app's own `wms_product_settings`. Both are
 * exposed as attributes so the API and admin payloads keep the shape their
 * clients already consume. See .ai/rules/shared-database.md.
 *
 * @property int $id
 * @property string $name
 * @property string $ar_name
 * @property int|null $thumbnail_img
 * @property-read string $display_name
 * @property-read string|null $image_url
 * @property-read int $boxes_count
 * @property-read Upload|null $thumbnailUpload
 * @property-read ProductSetting|null $setting
 */
#[Fillable(['name', 'ar_name', 'thumbnail_img'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The box count assumed for a product with no `wms_product_settings` row —
     * the store can add a product at any time without this app knowing, and a
     * pallet of one is the safe floor. Matches the default the old
     * `products.boxes_count` column carried.
     */
    public const int DEFAULT_BOXES_COUNT = 1;

    /**
     * Eager loads needed before reading `image_url` or `boxes_count`. Both are
     * relation-backed, so a payload that reads either without these will trip
     * the lazy-loading guard in local/testing.
     *
     * @var list<string>
     */
    public const array WITH_DERIVED_ATTRIBUTES = [
        'thumbnailUpload:id,file_name,external_link',
        'setting:product_id,boxes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // The store declares this varchar(100) though it holds an
            // `uploads` row id. Casting it keeps the relation working on
            // sqlite, which — unlike MySQL — will not match the string '5'
            // against the integer 5.
            'thumbnail_img' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Upload, $this>
     */
    public function thumbnailUpload(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'thumbnail_img');
    }

    /**
     * @return HasOne<ProductSetting, $this>
     */
    public function setting(): HasOne
    {
        return $this->hasOne(ProductSetting::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->thumbnailUpload?->url);
    }

    /**
     * The product name to render, following the request's active locale — the
     * session's for the admin panel, the `Accept-Language` header's for the
     * API (see SetLocale / SetLocaleFromHeader). Every payload this app
     * renders a product name from returns this, never the raw `name`.
     *
     * `?:` rather than `??` is load-bearing: upstream `ar_name` is
     * `varchar(191)` NOT NULL, so a product the store never translated carries
     * an empty string rather than null and still has to fall back to `name`.
     *
     * Reading this needs `ar_name` in the query's `select()`. Outside
     * production a query that leaves it out throws (strict mode's
     * `preventAccessingMissingAttributes` — see .ai/rules/app-providers.md);
     * in production the attribute reads as null and the label degrades to the
     * English `name` rather than erroring in front of a warehouse worker.
     *
     * `ar_name` is therefore read *before* the locale branch rather than
     * inside it, so that requirement doesn't itself depend on the locale. Read
     * lazily, a query missing the column would pass every English-locale test
     * and only throw once someone switched the panel to Arabic — which is
     * exactly the run nobody makes before merging.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(get: function (): string {
            $arabicName = $this->ar_name;

            return app()->isLocale(Locale::Arabic->value)
                ? ($arabicName ?: $this->name)
                : $this->name;
        });
    }

    /**
     * @return Attribute<int, never>
     */
    protected function boxesCount(): Attribute
    {
        // `->` rather than `?->`: the null-coalesce already applies isset
        // semantics to the whole left operand, so a product with no settings
        // row falls through to the default without a warning.
        return Attribute::make(get: fn (): int => $this->setting->boxes_count ?? self::DEFAULT_BOXES_COUNT);
    }

    /**
     * The id/name list used to populate the mobile app's product filter dropdown.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function filterOptions(): Collection
    {
        return self::optionLabels(self::query());
    }

    /**
     * The id/name pairs for the given product ids, used to hydrate an admin
     * product filter's already-selected labels without loading every product
     * (the admin filter fetches the searchable catalog on demand instead —
     * see Admin\ProductController::search()).
     *
     * @param  list<int>  $ids
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function selectedOptions(array $ids = []): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return self::optionLabels(self::query()->whereIn('id', $ids));
    }

    /**
     * Reduces an option-list query to the `{id, name}` pairs both dropdowns
     * consume, with `name` already resolved to the active locale's label.
     *
     * These two are the only product payloads that never pass through a
     * Resource, so the mapping to a plain array is what keeps `ar_name` — read
     * only to derive the label — out of the response, and what keeps the
     * emitted shape identical to the `ProductFilterOption` the frontend types.
     * Ordering deliberately stays on the base `name` column in both locales;
     * see .ai/rules/shared-database.md.
     *
     * @param  EloquentBuilder<Product>  $query
     * @return Collection<int, array{id: int, name: string}>
     */
    private static function optionLabels(EloquentBuilder $query): Collection
    {
        return $query
            ->select(['id', 'name', 'ar_name'])
            ->orderBy('name')
            ->get()
            // toBase() before map(): Eloquent's map() is declared as returning
            // `Support\Collection|static`, and a `static` of array shapes would
            // breach that class's `TModel of Model` bound under Larastan.
            ->toBase()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->display_name,
            ]);
    }

    /**
     * Scope a query to products whose name contains every word of the given
     * search term, in any order — so an admin searching "Blue Large" still
     * finds "Large Blue Widget" without knowing the words' actual order.
     * A blank/null term is a no-op, matching every product.
     *
     * Each word may match *either* the store's base `name` or its Arabic
     * `ar_name`, regardless of the request's locale. The locale is a
     * presentation choice — the session's for the admin panel, the
     * `Accept-Language` header's for the API (see SetLocaleFromHeader) — while
     * the term is whatever the warehouse staff actually typed, and they type
     * whichever of the two names they remember for a product. Scoping the
     * search to the active locale's column would make an identical term return
     * different results per device, and would return nothing at all for a
     * product whose `ar_name` the store left empty. Matching both columns
     * costs nothing here: an empty `ar_name` cannot match a non-empty word.
     *
     * The per-word grouping is load-bearing. A flat `orWhere()` chain would
     * bind the OR across word boundaries too, turning "every word matches" into
     * "any word matches" — so each word gets its own nested group, and the
     * groups are still ANDed together.
     *
     * @param  EloquentBuilder<Product>  $query
     */
    #[Scope]
    protected function searchByName(EloquentBuilder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $words = preg_split('/\s+/', trim($term)) ?: [];

        foreach ($words as $word) {
            $query->where(function (EloquentBuilder $matchesEitherName) use ($word): void {
                $matchesEitherName
                    ->where('name', 'like', '%'.$word.'%')
                    ->orWhere('ar_name', 'like', '%'.$word.'%');
            });
        }
    }
}
