<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use App;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
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

    public function getTranslation($field = '', $lang = false)
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

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function classified_products()
    {
        return $this->hasMany(CustomerProduct::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function childrenCategories()
    {
        return $this->hasMany(Category::class, 'parent_id')->with('categories');
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class);
    }

    public function views()
    {
        return $this->morphMany(UserPageView::class, 'viewable');
    }
}
