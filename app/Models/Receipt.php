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
 * A truck of pallets received into one warehouse against a bill. The truck
 * itself is not recorded. A draft receipt is edited while the admin checks the
 * pallets against the bill; finalizing it creates the real (unplaced) pallets
 * and freezes it. See .ai/rules/receiving.md.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $bill_name
 * @property string|null $bill_info
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
#[Fillable(['warehouse_id', 'bill_name', 'bill_info', 'status', 'created_by', 'finalized_by', 'finalized_at'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

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
     * @return HasMany<ReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class);
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
}
