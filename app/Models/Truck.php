<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    // //use hasFactory;

    protected $table = 'trucks';

    protected $fillable = [
        'name',
        'model',
        'max_pallets_number',
        'description',
        'license_plate',
        'photos',
        'is_available'
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function lastTrip()
    {
        return $this->trips()->where('status', '<>', 'closed')->latest('created_at')->first();
    }

    public function openTrips()
    {
        return $this->trips()->where('status', '<>', 'closed')->latest('created_at')->get();
    }


    public function scopeAvailable($query, $status = true)
    {
        return $query->where('is_available', $status);
    }
}
