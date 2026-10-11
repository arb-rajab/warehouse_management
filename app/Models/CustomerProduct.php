<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

class CustomerProduct extends Model
{
    /** @var list<string> */
    protected $with = ['customer_product_translations'];

    public function getTranslation(string $field = '', string|bool|null $lang = false): mixed
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $translation = $this->customer_product_translations->firstWhere('lang', $lang);

        return $translation !== null ? $translation->$field : $this->$field;
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class);
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<CustomerProductTranslation, $this> */
    public function customer_product_translations(): HasMany
    {
        return $this->hasMany(CustomerProductTranslation::class);
    }
}
