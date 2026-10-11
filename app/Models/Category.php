<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use App;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Category extends Model
{
    /** @use HasFactory<Factory<Category>> */
    use HasFactory;

    /**
     * No guarded attributes.
     *
     * The legacy model declared `protected $guarded;` with no value, which
     * redeclares the parent's `['*']` as null. Eloquent reads a null list as
     * empty, so the model was already fully mass assignable; spelling it `[]`
     * keeps that and removes the one trap the null set, mergeGuarded()'s
     * array_merge() on it throwing a TypeError.
     *
     * @var array<string>
     */
    protected $guarded = [];

    protected $with = ['category_translations'];

    public function getTranslation(string $field = '', string|false|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $category_translation = $this->category_translations->where('lang', $lang)->first();

        return $category_translation != null ? $category_translation->$field : $this->$field;
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function category_translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<CustomerProduct, $this> */
    public function classified_products(): HasMany
    {
        return $this->hasMany(CustomerProduct::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function childrenCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->with('categories');
    }

    /** @return BelongsTo<Category, $this> */
    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class);
    }

    /** @return MorphMany<UserPageView, $this> */
    public function views(): MorphMany
    {
        return $this->morphMany(UserPageView::class, 'viewable');
    }
}
