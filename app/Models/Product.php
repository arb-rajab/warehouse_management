<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image_url
 */
#[Fillable(['name', 'image_url'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The id/name list used to populate the admin product filter dropdowns.
     *
     * @return Collection<int, Product>
     */
    public static function filterOptions(): Collection
    {
        return self::query()->select(['id', 'name'])->orderBy('name')->get();
    }
}
