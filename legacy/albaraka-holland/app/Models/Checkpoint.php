<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    // //use hasFactory;

    protected $table = 'checkpoints';

    protected $fillable = [
        'trip_id',
        'relationable_id',
        'relationable_type',
        'type',
        'status',
        'arrived_at',
        'address',
        'longitudes',
        'latitudes',
        'sort_order_in_trip',
        'eta',
        'notes',
        'cmr_file',
        'photos'
    ];

    protected $casts = [
        'address' => 'object', // JSON field
        'arrived_at' => 'datetime',
        'eta' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($checkpoint) {

            if ($checkpoint->isOrder()) {
                $order = $checkpoint->getOrder();
                $order->delivery_status = 'ready_for_delivery';
                $order->save();
            }
        });
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    // Polymorphic relation: Can belong to any related model (Order, Customer)
    public function relationable()
    {
        return $this->morphTo();
    }

    public function isOrder()
    {
        return $this->relationable_type === Order::class;
    }

    public function isCustomer()
    {
        return $this->relationable_type === User::class;
    }

    public function getOrder()
    {
        return $this->isOrder() ? $this->relationable : null;

    }

    public function getCustomer()
    {
        return $this->isCustomer() ? $this->relationable : null;
    }

    public function getCustomerForCheckpoint()
    {
        if ($this->type === 'delivery' && $this->relationable_type === Order::class) {
            $order = $this->relationable;
            return $order?->customer;
        } else {
            return $this->relationable_type === User::class ? $this->relationable : null;
        }
    }


}
