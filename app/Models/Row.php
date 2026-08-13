<?php

namespace App\Models;

use App\Observers\RowObserver;
use Database\Factories\RowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * The rows + max column number shape shared by the admin cell and cell
     * status log filter dropdowns.
     *
     * @return array{rows: Collection<int, Row>, maxColumnNumber: int}
     */
    public static function filterOptions(): array
    {
        return [
            'rows' => self::query()->select(['id', 'letter'])->orderBy('letter')->get(),
            'maxColumnNumber' => (int) (self::query()->max('cells_count') ?? 0),
        ];
    }
}
