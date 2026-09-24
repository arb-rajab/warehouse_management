<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    
    protected $guarded = ['choice_attributes'];

    protected $with = ['product_translations', 'taxes', 'variations'];

    public function getTranslation($field = '', $lang = false)
    {
        $lang = $lang == false ? App::getLocale() : $lang;
        $product_translations = $this->product_translations->where('lang', $lang)->first();
        return $product_translations != null ? $product_translations->$field : $this->$field;
    }

    public function product_translations()
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function collection()
    {
        return $this->belongsTo(ProductCollection::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->where('status', 1);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function taxes()
    {
        return $this->hasMany(ProductTax::class);
    }

    public function flash_deal_product()
    {
        return $this->hasOne(FlashDealProduct::class);
    }

    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'offer_products', 'product_id', 'offer_id')
            ->wherePivot('is_base_product', 1);
    }

    public function bids()
    {
        return $this->hasMany(AuctionProductBid::class);
    }

    public function scopePhysical($query)
    {
        return $query->where('digital', 0);
    }

    public function scopeDigital($query)
    {
        return $query->where('digital', 1);
    }

    public function variations()
    { // product_variations table
        return $this->hasMany(ProductVariation::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    // public function getPhotosAttribute()
    // {
    //     // Check if Store photos are null, if yes, return Otajer image
    //     if (is_null($this->attributes['photos'])) {
    //         return $this->attributes['old_photo'];
    //     }

    //     // Otherwise, return photos
    //     return $this->attributes['photos'];
    // }

    // public function getThumbnailImgAttribute()
    // {
    //     // Check if Store thumbnail_img is null
    //     if (is_null($this->attributes['thumbnail_img'])) {

    //         // if Store photos is null
    //         if(is_null($this->attributes['photos'])){

    //             // return Otajer image
    //             return $this->attributes['old_photo'];
    //         }

    //         // if Store photos is not null, return photos
    //         return $this->attributes['photos'];
    //     }

    //     // Otherwise, return photos
    //     return $this->attributes['thumbnail_img'];
    // }

    public function profit()
    {
        $profit = (float)($this->unit_price * $this->collection?->amount ?? 0) / 100;
        return $profit;
    }

    public function views()
    {
        return $this->morphMany(UserPageView::class, 'viewable');
    }
}
