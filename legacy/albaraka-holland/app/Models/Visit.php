<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    //use hasFactory;

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function rep()
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    public function get_last_visit_order_in_same_day()
    {
        // Get the date part (without time) of the current record's created_at timestamp
        $currentDate = $this->created_at->toDateString();

        // Retrieve the last record where the date part of created_at matches $currentDate
        return $this->whereDate('created_at', $currentDate)
            ->latest()  // Latest record (last created)
            ->first();  // Retrieve only the first matching record
    }
}
