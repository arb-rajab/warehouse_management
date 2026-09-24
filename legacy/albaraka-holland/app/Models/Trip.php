<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Trip extends Model
{
    // //use hasFactory;

    protected $table = 'trips';

    protected $fillable = [
        'driver_id',
        'truck_id',
        'due_date',
        'status',
        'active',
        'started_at',
        'closed_at',
        'code',
        'notes',
        'distance',
        'duration'
    ];

    protected $casts = [
        'due_date' => 'date',
        'active' => 'boolean',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'distance' => 'decimal:2', // Two decimal places
        'duration' => 'decimal:2',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }

    public function checkpoints()
    {
        return $this->hasMany(Checkpoint::class, 'trip_id', 'id')->orderBy('sort_order_in_trip');
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('active', true);
    }

    public function scopeSearch($query, $searchTerm)
    {
        $query->where(function ($query) use ($searchTerm) {
            $query->where('code', $searchTerm)
                ->orWhere('status', 'like', '%' . $searchTerm . '%')
                ->orWhereHas('driver', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                })
                ->orWhereHas('truck', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%');
                })
                ->orWhereHas('checkpoints', function ($q) use ($searchTerm) {
                    $q->whereHasMorph('relationable', [User::class, Order::class], function ($q, $type) use ($searchTerm) {
                        if ($type === User::class) {
                            $q->where('name', 'LIKE', '%' . $searchTerm . '%');
                        } elseif ($type === Order::class) {
                            $q->whereHas('user', function ($q) use ($searchTerm) {
                                $q->where('name', 'LIKE', '%' . $searchTerm . '%');
                            });
                        }
                    });
                });
        });
    }
}
