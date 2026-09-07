<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image_url
 * @property int $boxes_count
 */
#[Fillable(['name', 'image_url', 'boxes_count'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The id/name list used to populate the mobile app's product filter dropdown.
     *
     * @return Collection<int, Product>
     */
    public static function filterOptions(): Collection
    {
        return self::query()->select(['id', 'name'])->orderBy('name')->get();
    }

    /**
     * The id/name pairs for the given product ids, used to hydrate an admin
     * product filter's already-selected labels without loading every product
     * (the admin filter fetches the searchable catalog on demand instead —
     * see Admin\ProductController::search()).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public static function selectedOptions(array $ids = []): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return self::query()->select(['id', 'name'])->whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Scope a query to products whose name contains every word of the given
     * search term, in any order — so an admin searching "Blue Large" still
     * finds "Large Blue Widget" without knowing the words' actual order.
     * A blank/null term is a no-op, matching every product.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function searchByName(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $words = preg_split('/\s+/', trim($term)) ?: [];

        foreach ($words as $word) {
            $query->where('name', 'like', '%'.$word.'%');
        }
    }
}
