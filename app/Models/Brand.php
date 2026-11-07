<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

/**
 * @property int $id
 * @property string $name
 * @property string|null $logo
 * @property int $top
 * @property string|null $slug
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $serial
 * @property-read Collection<int, BrandTranslation> $brand_translations
 */
class Brand extends Model
{
    protected $with = ['brand_translations'];

    public function getTranslation(string $field = '', string|false|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $brand_translation = $this->brand_translations->where('lang', $lang)->first();

        return $brand_translation != null ? $brand_translation->getAttribute($field) : $this->getAttribute($field);
    }

    /**
     * The categories where each of these brands has products a shopper can
     * see: the products the store API lists and searches (published, approved,
     * with a stock row, physical, from a seller the shop shows), by the id of
     * the category the product is in. That is the match
     * `GET /products/search?categories={id}&brands={brandId}` makes, so a
     * category named here has at least one result there. A brand with none
     * has an empty list.
     *
     * @param  array<int, int|string>  $brandIds
     * @return array<int, list<int>> category ids, ascending, by brand id
     */
    public static function categoryIdsFor(array $brandIds): array
    {
        $ids = array_map('intval', $brandIds);
        /** @var array<int, list<int>> $byBrand */
        $byBrand = array_fill_keys($ids, []);

        if ($ids === []) {
            return $byBrand;
        }

        $pairs = filter_products(Product::query()->storeOpenable()->physical()->whereIn('brand_id', $ids))
            ->whereNotNull('category_id')
            ->select(['brand_id', 'category_id'])
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $byBrand[(int) $pair->brand_id][] = (int) $pair->category_id;
        }

        foreach ($byBrand as $brandId => $categoryIds) {
            sort($categoryIds);
            $byBrand[$brandId] = $categoryIds;
        }

        return $byBrand;
    }

    /** @return HasMany<BrandTranslation, $this> */
    public function brand_translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
