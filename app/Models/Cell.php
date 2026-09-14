<?php

namespace App\Models;

use App\Enums\CellState;
use Database\Factories\CellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $row_id
 * @property int $cell_number
 * @property int $flat_number
 * @property CellState $state
 * @property bool $is_active
 */
#[Fillable(['row_id', 'cell_number', 'flat_number', 'state', 'is_active'])]
class Cell extends Model
{
    /** @use HasFactory<CellFactory> */
    use HasFactory;

    /**
     * Columns needed by CellResource — shared by every listing (admin and API).
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = ['id', 'row_id', 'cell_number', 'flat_number', 'state', 'is_active'];

    /**
     * Eager loads needed to describe what a cell currently holds.
     *
     * @var list<string>
     */
    public const array WITH_CONTENTS = [
        'pallet:id,cell_id,product_id,expiration_date,remaining_boxes,created_at',
        'pallet.product:id,name,ar_name,thumbnail_img,published',
        'pallet.product.thumbnailUpload:id,file_name,external_link',
        'pallet.cellEnteredLog:id,pallet_id,action,created_at',
    ];

    /**
     * As WITH_CONTENTS, plus the parent row for cells not already queried through one.
     *
     * @var list<string>
     */
    public const array WITH_ROW_AND_CONTENTS = ['row:id,letter', ...self::WITH_CONTENTS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => CellState::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Row, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(Row::class);
    }

    /**
     * @return HasOne<Pallet, $this>
     */
    public function pallet(): HasOne
    {
        return $this->hasOne(Pallet::class);
    }

    /**
     * The row letter + cell number + flat number shape used to describe this
     * cell's location wherever a Resource exposes it.
     *
     * @return array{row_letter: string, cell_number: int, flat_number: int}
     */
    public function toLocationArray(): array
    {
        return [
            'row_letter' => $this->row->letter,
            'cell_number' => $this->cell_number,
            'flat_number' => $this->flat_number,
        ];
    }

    /**
     * The human-readable "A1·2" label printed on QR-export PDFs and shown on
     * the page a scanned QR code redirects to — both must stay in lockstep.
     */
    public static function slotLabel(string $rowLetter, int $cellNumber, int $flatNumber): string
    {
        return "{$rowLetter}{$cellNumber}·{$flatNumber}";
    }

    /**
     * Scope a query to the single cell identified by a row's human-readable coordinates.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function atCoordinates(Builder $query, Row $row, int $cellNumber, int $flatNumber): void
    {
        $query->where('row_id', $row->id)
            ->where('cell_number', $cellNumber)
            ->where('flat_number', $flatNumber);
    }

    /**
     * Scope a query to the order cells are laid out in for display.
     *
     * @param  Builder<Cell>  $query
     */
    #[Scope]
    protected function orderedByCoordinates(Builder $query): void
    {
        $query->orderBy('cell_number')->orderBy('flat_number');
    }
}
