<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A truck of pallets received into one warehouse. The truck itself is not
 * recorded and the bill is kept only as its photos/PDFs (`files`): no bill
 * details are stored. While the receipt is a draft the admin records the
 * pallets as written on the bill (`pallets`, checked against the bill by
 * eye); finalizing it creates the real (unplaced) pallets and freezes it.
 * See .ai/rules/receiving.md.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string|null $note
 * @property ReceiptStatus $status
 * @property int $created_by
 * @property int|null $finalized_by
 * @property CarbonImmutable|null $finalized_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read User|null $finalizedBy
 */
#[Fillable(['warehouse_id', 'note', 'status', 'created_by', 'finalized_by', 'finalized_at'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /**
     * The columns ReceiptResource reads off a receipt.
     *
     * @var list<string>
     */
    public const array SELECT_COLUMNS = [
        'id', 'warehouse_id', 'note', 'status',
        'created_by', 'finalized_by', 'finalized_at', 'created_at', 'updated_at',
    ];

    /**
     * Eager loads ReceiptResource reads on every receipt, listed or shown.
     *
     * @var list<string>
     */
    public const array WITH_SUMMARY = [
        'warehouse:id,name',
        'createdBy:id,name',
        'finalizedBy:id,name',
    ];

    /**
     * Eager loads of a receipt's detail: the summary plus its files (never
     * their storage path) in the order they were added, and its pallets as
     * written on the bill with their product, in the order the admin typed
     * them (never the id of the pallet finalizing created).
     *
     * @return array<int|string, mixed>
     */
    public static function withDetails(): array
    {
        return [
            ...self::WITH_SUMMARY,
            'files' => fn (HasMany $query) => $query
                ->select(['id', 'receipt_id', 'original_name', 'mime_type', 'size', 'created_at'])
                ->orderBy('id'),
            'pallets' => fn (HasMany $query) => $query
                ->select(['id', 'receipt_id', 'product_id', 'boxes', 'expiration_date', 'note', 'position'])
                ->with([
                    'product:id,name,ar_name,thumbnail_img,warehouse_image_path,published',
                    'product.thumbnailUpload:id,file_name,external_link',
                    'product.setting:product_id,boxes_count',
                ])
                ->orderBy('position')
                ->orderBy('id'),
        ];
    }

    /**
     * A receipt created without a status is a draft, as in the column default.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReceiptStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withTrashed();
    }

    /**
     * @return HasMany<ReceiptFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ReceiptFile::class);
    }

    /**
     * The pallets as written on the bill (one entry per pallet), linked to
     * the real pallet once the receipt is finalized.
     *
     * @return HasMany<ReceiptPallet, $this>
     */
    public function pallets(): HasMany
    {
        return $this->hasMany(ReceiptPallet::class);
    }

    /**
     * Whether the receipt has been finalized — a finalized receipt is no
     * longer editable; corrections use the normal pallet actions.
     */
    public function isFinalized(): bool
    {
        return $this->status === ReceiptStatus::Finalized;
    }

    /**
     * Scope a query to the receipts of the given warehouses; null (no
     * warehouse filter) matches every receipt. See {@see Row::forWarehouses()}.
     *
     * @param  Builder<Receipt>  $query
     * @param  list<int>|null  $warehouseIds
     */
    #[Scope]
    protected function forWarehouses(Builder $query, ?array $warehouseIds): void
    {
        if ($warehouseIds !== null) {
            $query->whereIn($query->qualifyColumn('warehouse_id'), $warehouseIds);
        }
    }

    /**
     * Scope a query to the drafts or to the finalized receipts; null (no
     * status filter) matches every receipt.
     *
     * @param  Builder<Receipt>  $query
     */
    #[Scope]
    protected function ofStatus(Builder $query, ?ReceiptStatus $status): void
    {
        if ($status !== null) {
            $query->where($query->qualifyColumn('status'), $status->value);
        }
    }
}
