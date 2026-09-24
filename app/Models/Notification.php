<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //use hasFactory;
    protected $fillable = ['user_id', 'title', 'body', 'status', 'response'];
    protected $table = "customers_notifications";

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
