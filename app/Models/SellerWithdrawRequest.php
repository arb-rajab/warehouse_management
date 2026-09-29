<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWithdrawRequest extends Model
{
    public function user()
    {
        return $this->belongsTo(StoreUser::class);
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
