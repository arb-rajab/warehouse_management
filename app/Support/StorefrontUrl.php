<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Read-only addresses and matching lookups for the three storefront catalogue routes. */
final class StorefrontUrl
{
    public const array PARAMETERS = ['product' => 'slug', 'products.category' => 'category_slug', 'products.brand' => 'brand_slug'];

    public static function to(Product|Category|Brand $record, bool $absolute = true): string
    {
        $route = match (true) {
            $record instanceof Product => 'product',
            $record instanceof Category => 'products.category',
            $record instanceof Brand => 'products.brand',
        };

        return self::forRow($route, $record->getAttribute('slug'), (int) $record->getKey(), $absolute);
    }

    /** Also accepts the sitemap's streamed rows, without hydrating models or querying. */
    public static function forRow(string $route, ?string $slug, int $id, bool $absolute = true): string
    {
        $parameters = $slug === null || $slug === '' ? ['~id-'.$id, 'catalog_id' => $id] : [$slug];

        return route($route, $parameters, $absolute);
    }

    /**
     * Keep every caller's visibility constraints. The discriminator makes even
     * an existing literal "~id-N" slug a different address from an ID fallback.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public static function resolve(Builder $query, string $identifier, Request $request): Builder
    {
        $id = self::fallbackId($identifier, $request);

        return $id === null
            ? $query->where('slug', $identifier)
            : $query->whereKey($id)->where(fn (Builder $missing) => $missing->whereNull('slug')->orWhere('slug', ''));
    }

    /** The same address in canonical tags, preserving the ID discriminator only when it identifies the path. */
    public static function requestPath(Request $request): ?string
    {
        $route = $request->route();
        $name = $route?->getName();
        if (! isset(self::PARAMETERS[$name])) {
            return null;
        }
        $identifier = $route->parameter(self::PARAMETERS[$name]);
        if (! is_string($identifier)) {
            return null;
        }
        $id = self::fallbackId($identifier, $request);

        return self::forRow($name, $id === null ? $identifier : null, (int) $id, false);
    }

    private static function fallbackId(string $identifier, Request $request): ?string
    {
        $id = $request->query('catalog_id');

        return is_string($id) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $id) === 1 && $identifier === '~id-'.$id ? $id : null;
    }
}
