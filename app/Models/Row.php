<?php

namespace App\Models;

use App\Observers\RowObserver;
use Database\Factories\RowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $letter
 * @property int $cells_count
 * @property int $flats_count
 */
#[Fillable(['letter', 'cells_count', 'flats_count'])]
#[ObservedBy(RowObserver::class)]
#[RouteKey('letter')]
class Row extends Model
{
    /** @use HasFactory<RowFactory> */
    use HasFactory;

    /**
     * @return HasMany<Cell, $this>
     */
    public function cells(): HasMany
    {
        return $this->hasMany(Cell::class);
    }

    /**
     * Whether any of this row's cells currently hold a pallet.
     */
    public function hasPallets(): bool
    {
        return $this->cells()->whereHas('pallet')->exists();
    }
}
