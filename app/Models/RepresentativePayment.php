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
        return $this->belongsTo(User::class, 'rep_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
