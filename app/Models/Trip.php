<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
        'duration',
    ];

    protected $casts = [
        'due_date' => 'date',
        'active' => 'boolean',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'distance' => 'decimal:2', // Two decimal places
        'duration' => 'decimal:2',
    ];

    /**
     * Eager loads for TripResource: the driver and truck, and for every
     * checkpoint the order or customer it stops at, with an order's lines,
     * their products and the customer the order is for.
     *
     * @return array<int|string, string|\Closure>
     */
    public static function resourceRelations(): array
    {
        return [
            'driver',
            'truck',
            'checkpoints.relationable' => fn (MorphTo $relationable) => $relationable->morphWith([
                Order::class => ['orderDetails.product', 'user', 'rep'],
            ]),
        ];
    }

    /** @return BelongsTo<StoreUser, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(StoreUser::class, 'driver_id');
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }

    /** @return HasMany<Checkpoint, $this> */
    public function checkpoints(): HasMany
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
                ->orWhere('status', 'like', '%'.$searchTerm.'%')
                ->orWhereHas('driver', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                })
                ->orWhereHas('truck', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');
                })
                ->orWhereHas('checkpoints', function ($q) use ($searchTerm) {
                    $q->whereHasMorph('relationable', [StoreUser::class, Order::class], function ($q, $type) use ($searchTerm) {
                        if ($type === StoreUser::class) {
                            $q->where('name', 'LIKE', '%'.$searchTerm.'%');
                        } elseif ($type === Order::class) {
                            $q->whereHas('user', function ($q) use ($searchTerm) {
                                $q->where('name', 'LIKE', '%'.$searchTerm.'%');
                            });
                        }
                    });
                });
        });
    }
}
