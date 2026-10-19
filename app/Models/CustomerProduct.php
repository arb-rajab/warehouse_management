<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProduct extends Model
{
    protected $with = ['customer_product_translations'];

    public function getTranslation($field = '', $lang = false){
      $lang = $lang == false ? App::getLocale() : $lang;
      $customer_product_translations = $this->customer_product_translations->where('lang', $lang)->first();
      return $customer_product_translations != null ? $customer_product_translations->$field : $this->$field;
    }

    /** @return BelongsTo<Upload, $this> */
    public function thumbnailUpload(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'thumbnail_img');
    }

    public function category(){
    	return $this->belongsTo(Category::class);
    }

    public function brand(){
    	return $this->belongsTo(Brand::class);
    }

    public function user(){
    	return $this->belongsTo(StoreUser::class);
    }

    public function state(){
    	return $this->belongsTo(State::class);
    }

    public function city(){
    	return $this->belongsTo(City::class);
    }

    /** @return HasMany<CustomerProductTranslation, $this> */
    public function customer_product_translations(): HasMany {
      return $this->hasMany(CustomerProductTranslation::class);
    }
}
