<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * A row in this app's `products` table.
 *
 * This app owns the table, but not the product data in it: `name`, `ar_name`
 * and `published` are written only by `products:sync`
 * (App\Console\Commands\SyncProductsCommand), periodically and in bulk, from
 * the Otajer store's feed — see the "Product sync" section of
 * .ai/rules/shared-database.md. That command is the single writer, so there is
 * no per-request create/update/delete of a `Product` anywhere in this codebase
 * and there should not be. The remaining columns are inherited from the store's
 * original schema and are never read here.
 *
 * `image_url` and `boxes_count` are not columns on it: the first resolves
 * through the store-owned `uploads` table, the second through this app's own
 * `wms_product_settings`. Both are exposed as attributes so the API and
 * admin payloads keep the shape their clients already consume.
 *
 * @property int $id
 * @property string $name
 * @property string $ar_name
 * @property int|null $thumbnail_img
 * @property bool $published
 * @property-read string|null $image_url
 * @property-read int $boxes_count
 * @property-read int|null $minimum_pallets
 * @property-read Upload|null $thumbnailUpload
 * @property-read ProductSetting|null $setting
 */
#[Fillable(['name', 'ar_name', 'thumbnail_img', 'published'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The box count assumed for a product with no `wms_product_settings` row —
     * the store can add a product at any time without this app knowing, and
     * this is the warehouse's default pallet size until someone configures it.
     */
    public const int DEFAULT_BOXES_COUNT = 50;

    /**
     * Eager loads needed before reading `image_url` or `boxes_count`. Both are
     * relation-backed, so a payload that reads either without these will trip
     * the lazy-loading guard in local/testing.
     *
     * @var list<string>
     */
    public const array WITH_DERIVED_ATTRIBUTES = [
        'thumbnailUpload:id,file_name,external_link',
        'setting:product_id,boxes_count,minimum_pallets',
    ];

    /**
     * The columns a product search matches, in the order the FULLTEXT index
     * declares them — `MATCH` only resolves against an index covering exactly
     * this column list, so the two must stay in step with
     * `add_fulltext_index_to_products_table`.
     *
     * @var list<string>
     */
    public const array SEARCHABLE_NAME_COLUMNS = ['name', 'ar_name'];

    /**
     * The drivers whose grammar can compile `MATCH ... AGAINST`. Everything
     * else — sqlite, the test connection — falls back to `LIKE`, since
     * Laravel's base query grammar throws outright on `whereFullText()`.
     *
     * @var list<string>
     */
    private const array FULL_TEXT_DRIVERS = ['mysql', 'mariadb'];

    /**
     * Mirrors MySQL's `innodb_ft_min_token_size` default: a shorter word is
     * not in the FULLTEXT index, so `+ab*` matches nothing rather than
     * matching less. A deployment that tunes the server variable downwards can
     * lower this to match — it only ever costs a `LIKE` on words it excludes,
     * never a wrong result.
     */
    public const int FULL_TEXT_MIN_WORD_LENGTH = 3;

    /**
     * InnoDB's default full-text stopword list
     * (`information_schema.innodb_ft_default_stopword`, 36 entries). These are
     * stripped from a boolean-mode query, so `+for*` matches nothing at all —
     * they stay on `LIKE`. The entries shorter than
     * {@see self::FULL_TEXT_MIN_WORD_LENGTH} are already excluded by length;
     * the full list is kept so it reads as the upstream list rather than an
     * arbitrary subset.
     *
     * @var list<string>
     */
    private const array FULL_TEXT_STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en',
        'for', 'from', 'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or',
        'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who',
        'will', 'with', 'und', 'www',
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
            // The store declares this `int(11)` (0/1), not a real boolean
            // column type.
            'published' => 'boolean',
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
     * How many pallets of this product should be in the warehouse at
     * minimum, or `null` when nobody has configured one. Unlike
     * {@see self::boxesCount()} there is no fallback default: "not
     * configured" must never be treated as "always below the minimum" — a
     * product only counts as low-stock once an admin has actually set a
     * threshold for it (see Admin\ProductController::updateMinimumPallets()).
     *
     * @return Attribute<int|null, never>
     */
    protected function minimumPallets(): Attribute
    {
        return Attribute::make(get: fn (): ?int => $this->setting->minimum_pallets ?? null);
    }

    /**
     * The id/name list used to populate the mobile app's product filter dropdown.
     *
     * @return Collection<int, array{id: int, name: string, ar_name: string}>
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
     * @return Collection<int, array{id: int, name: string, ar_name: string}>
     */
    public static function selectedOptions(array $ids = []): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return self::optionLabels(self::query()->whereIn('id', $ids));
    }

    /**
     * Reduces an option-list query to the `{id, name, ar_name}` triples both
     * dropdowns consume — both store columns raw, with the label picked
     * client-side by `lib/productName.ts` from the active locale.
     *
     * These two are the only product payloads that never pass through a
     * Resource, so the mapping to a plain array is what keeps the emitted
     * shape identical to the `ProductFilterOption` the frontend types, rather
     * than serialising whole models. `ar_name` is part of that shape now: the
     * frontend needs the column itself, not a label derived from it here.
     * Ordering deliberately stays on the base `name` column in both locales;
     * see .ai/rules/shared-database.md.
     *
     * @param  EloquentBuilder<Product>  $query
     * @return Collection<int, array{id: int, name: string, ar_name: string}>
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
                'name' => $product->name,
                'ar_name' => $product->ar_name,
            ]);
    }

    /**
     * Scope a query to products matching every word of the given search term,
     * in any order — so an admin searching "Blue Large" still finds "Large
     * Blue Widget" without knowing the words' actual order. A blank/null term
     * is a no-op, matching every product.
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
     * A term that is entirely digits also matches products whose numeric
     * `id` contains it as a substring (see
     * `applyNameSearch()`/`searchTermAsProductId()`) — additive to, never a
     * replacement for, the name match.
     *
     * @param  EloquentBuilder<Product>  $query
     */
    #[Scope]
    protected function searchByName(EloquentBuilder $query, ?string $term): void
    {
        self::applyNameSearch($query->getQuery(), $term);
    }

    /**
     * Apply the product name search to a *query* builder rather than an
     * Eloquent one.
     *
     * `Api\V1\CellController::index()` needs the same matching from inside a
     * `whereHas('pallet.product', ...)` closure, where calling this model's
     * `#[Scope]` directly loses its generic type under Larastan (see the
     * "Don't call another model's #[Scope] inside whereHas()" rule in
     * .ai/rules/models.md). Taking the underlying `Query\Builder` — which the
     * Eloquent builder merely wraps, so the conditions land identically —
     * sidesteps the generics entirely and keeps one implementation for both
     * callers.
     */
    public static function applyNameSearch(QueryBuilder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        // A term that is nothing but digits is also treated as a (possibly
        // partial) product id: warehouse staff frequently remember only part
        // of the numeric id (e.g. off a printed label) rather than the whole
        // thing or the name. This is additive — it never narrows the existing
        // name match, only OR's an `id LIKE '%digits%'` match onto it, so a
        // term with no digit-only reading leaves the query exactly as it was
        // before this existed.
        $productIdFragment = self::searchTermAsProductId($term);

        // Query\Builder::getConnection() is declared as ConnectionInterface,
        // which has no getDriverName() — only the concrete Connection does. A
        // connection that is neither falls back to LIKE, which is the safe
        // default here: the driver check exists to ask whether this grammar can
        // compile MATCH at all, and an unknown one cannot be assumed to.
        $connection = $query->getConnection();

        $plan = self::nameSearchPlan(
            $term,
            $connection instanceof Connection ? $connection->getDriverName() : '',
        );

        if ($productIdFragment === null) {
            self::applyNameMatch($query, $plan);

            return;
        }

        $query->where(function (QueryBuilder $group) use ($plan, $productIdFragment): void {
            self::applyNameMatch($group, $plan);
            $group->orWhere('id', 'like', "%{$productIdFragment}%");
        });
    }

    /**
     * Apply just the name-matching half of the search (FULLTEXT-or-LIKE, or
     * plain LIKE where the driver/term rules FULLTEXT out) — the same
     * condition `applyNameSearch()` always applied before it also learned to
     * match by id. Split out so the id-less path keeps compiling to exactly
     * the SQL it always did, with no extra wrapping group.
     *
     * @param  array{full_text_expression: string|null, like_words: list<string>}  $plan
     */
    private static function applyNameMatch(QueryBuilder $query, array $plan): void
    {
        // No FULLTEXT expression at all — either this driver can't compile
        // MATCH, or the term has no indexable word — so the LIKE-AND-of-words
        // group is the whole name condition, exactly as it always was on
        // sqlite.
        if ($plan['full_text_expression'] === null) {
            self::applyLikeWords($query, $plan['like_words']);

            return;
        }

        // Otherwise a product matches this driver's search if it satisfies
        // *either* strategy: the FULLTEXT expression (prefix matching, but
        // gapless past the length/stopword/punctuation exclusions below) or
        // the same LIKE-AND-of-words group sqlite would run (infix matching
        // over every word, with no exclusions). OR'ing the two whole
        // strategies — rather than routing each word to exactly one of them —
        // is what makes the combined search a superset of the LIKE search:
        // "idget" only ever matches "Widget" through this LIKE branch, never
        // through FULLTEXT, whichever branch a given word's own FULLTEXT
        // eligibility would have picked.
        $query->where(function (QueryBuilder $group) use ($plan): void {
            $group->whereFullText(self::SEARCHABLE_NAME_COLUMNS, $plan['full_text_expression'], ['mode' => 'boolean']);
            $group->orWhere(function (QueryBuilder $likeGroup) use ($plan): void {
                self::applyLikeWords($likeGroup, $plan['like_words']);
            });
        });
    }

    /**
     * Parse a search term as a product id fragment: the whole trimmed term
     * must be nothing but digits, so "42" matches but "42 widgets", "-42" and
     * "4.2" do not — those are names/decimals, not an id lookup. The digits
     * are returned as-is (not cast to int) so a leading zero the admin typed
     * (e.g. "007") stays part of the substring pattern rather than being
     * silently dropped.
     */
    private static function searchTermAsProductId(string $term): ?string
    {
        $trimmed = trim($term);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * AND together one `LIKE '%word%'` group per word, each word matching
     * *either* store name column.
     *
     * The per-word grouping is load-bearing. A flat `orWhere()` chain would
     * bind the OR across word boundaries too, turning "every word matches"
     * into "any word matches" — so each word gets its own nested group, and
     * the groups are still ANDed together.
     *
     * The word is escaped before it is wrapped in `%...%`, so a literal `%`
     * or `_` the user typed is matched as a literal character rather than as
     * a wildcard (`%` would otherwise match every product; `a_b` would match
     * "aXb"). Both drivers need an explicit `ESCAPE` clause for this to take
     * effect — sqlite recognizes no escape character at all without one, so
     * this uses `whereRaw()`/`orWhereRaw()` rather than the plain `like`
     * operator.
     *
     * @param  list<string>  $words
     */
    private static function applyLikeWords(QueryBuilder $query, array $words): void
    {
        foreach ($words as $word) {
            $pattern = '%'.self::escapeLikeWildcards($word).'%';

            $query->where(function (QueryBuilder $matchesEitherName) use ($pattern): void {
                $matchesEitherName
                    ->whereRaw('`name` like ? escape ?', [$pattern, '\\'])
                    ->orWhereRaw('`ar_name` like ? escape ?', [$pattern, '\\']);
            });
        }
    }

    /**
     * Escape the LIKE wildcard characters (`%`, `_`) and the escape character
     * itself (`\`) in a raw search word, so it can be safely wrapped in
     * `%...%` and matched literally via `LIKE ... ESCAPE '\'`.
     */
    private static function escapeLikeWildcards(string $word): string
    {
        return addcslashes($word, '\\%_');
    }

    /**
     * Build the FULLTEXT half of the search: a single boolean-mode expression
     * covering only the term's *indexable* words, or `null` when it has none.
     *
     * `MATCH (name, ar_name) AGAINST ('+w1* +w2*' IN BOOLEAN MODE)` requires
     * every included word (the per-word AND) in *either* indexed column (the
     * across-columns OR, since MySQL treats them as one document). Matching is
     * case-insensitive under the columns' collation, as `LIKE` is.
     *
     * `isFullTextIndexable()` excludes three kinds of word, because boolean
     * mode would silently return *nothing* for them rather than fewer rows —
     * and unlike the old routing, an excluded word here costs nothing: it is
     * still covered by the LIKE-AND-of-every-word group `applyNameSearch()`
     * ORs against this expression, over every word regardless of exclusion:
     *
     * - **Anything but letters and digits.** MySQL's default parser splits a
     *   word on every other character, so `+Wid-get*` could never match the
     *   product literally named "Wid-get". Excluding these also keeps this
     *   expression injection-proof: it is built solely from alphanumeric words
     *   plus the `+` and `*` this method adds, so a term containing boolean
     *   operators (`-`, `"`, `~`, `(`) cannot reach the parser and invert or
     *   break the query.
     * - **Words shorter than `innodb_ft_min_token_size`.** Such words are not
     *   in the index at all. This matters most for the admin's type-ahead
     *   dropdown, where a one- or two-letter term is the common case.
     * - **InnoDB's default stopwords.** `+for*` matches nothing, which would
     *   lose every result for a term like "Case for Phone" through this
     *   expression alone.
     *
     * The one respect in which the combined search still differs from a pure
     * `LIKE`: an indexable word matches by *prefix* through this expression
     * ("Widg" finds "Widgets") in addition to matching as an infix through the
     * LIKE branch — so the combined search is a strict superset of `LIKE`
     * alone, never narrower.
     *
     * @param  list<string>  $words
     */
    private static function fullTextExpression(array $words): ?string
    {
        $indexedWords = array_values(array_filter(array_map(
            static fn (string $word): ?string => self::isFullTextIndexable($word) ? '+'.$word.'*' : null,
            $words,
        )));

        return $indexedWords === [] ? null : implode(' ', $indexedWords);
    }

    /**
     * Plan the two search strategies `applyNameSearch()` OR's together on
     * mysql/mariadb: the FULLTEXT expression from
     * {@see self::fullTextExpression()}, and the full list of words for the
     * LIKE-AND-of-every-word group. On every other driver — sqlite, the test
     * connection — there is no FULLTEXT support at all, so every word goes
     * only to the LIKE group, which becomes the whole search.
     *
     * @param  string  $driver  the connection's driver — sqlite (the test
     *                          connection) has no `MATCH ... AGAINST` at all,
     *                          so the FULLTEXT half is skipped there
     * @return array{full_text_expression: string|null, like_words: list<string>}
     */
    private static function nameSearchPlan(string $term, string $driver): array
    {
        /** @var list<string> $words */
        $words = preg_split('/\s+/', trim($term)) ?: [];

        if (! in_array($driver, self::FULL_TEXT_DRIVERS, true)) {
            return ['full_text_expression' => null, 'like_words' => $words];
        }

        return [
            'full_text_expression' => self::fullTextExpression($words),
            'like_words' => $words,
        ];
    }

    /**
     * Whether boolean-mode `MATCH ... AGAINST` can answer this word at all —
     * see the three exclusions documented on {@see self::fullTextExpression()}.
     */
    private static function isFullTextIndexable(string $word): bool
    {
        return preg_match('/^[\p{L}\p{N}]+$/u', $word) === 1
            && mb_strlen($word) >= self::FULL_TEXT_MIN_WORD_LENGTH
            && ! in_array(mb_strtolower($word), self::FULL_TEXT_STOPWORDS, true);
    }
}
