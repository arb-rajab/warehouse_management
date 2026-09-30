<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepresentativePayment extends Model
{
    //use hasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function rep()
    {
        return $this->belongsTo(StoreUser::class, 'rep_id');
    }

    public function customer()
    {
        return $this->belongsTo(StoreUser::class, 'customer_id');
    }

    public function admin()
    {
        return $this->belongsTo(StoreUser::class, 'admin_id');
    }
}
