<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A warehouse the WMS manages. It owns rows; cells and pallets belong to a
 * warehouse through their row. Every WMS user sees every warehouse.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property-read int|null $rows_count
 */
#[Fillable(['name', 'code', 'address'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    /**
     * The warehouse `2026_11_08_120001_add_warehouse_id_to_rows_table` moved
     * every existing row into.
     */
    public const string DEFAULT_NAME = 'Default';

    /**
     * @return HasMany<Row, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(Row::class);
    }

    /**
     * Whether any row still belongs to this warehouse — a warehouse with rows
     * cannot be deleted (WarehouseController::destroy(), and the
     * `restrictOnDelete` foreign key behind it).
     */
    public function hasRows(): bool
    {
        return $this->rows()->exists();
    }

    /**
     * The "Default" warehouse, created when missing. For seeders and the
     * row factory, which need a warehouse for rows created without one.
     */
    public static function firstOrCreateDefault(): self
    {
        return self::query()->firstOrCreate(['name' => self::DEFAULT_NAME]);
    }

    /**
     * Scope a query to warehouses whose name or code contains the term; a
     * blank term matches every warehouse.
     *
     * @param  Builder<Warehouse>  $query
     */
    #[Scope]
    protected function searchByName(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
    }

    /**
     * The `{id, name}` labels of the already-selected warehouses, for a
     * warehouse filter that fetches the rest on demand — the warehouse
     * counterpart of {@see Product::selectedOptions()}.
     *
     * @param  list<int>  $ids
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function selectedOptions(array $ids = []): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return self::query()
            ->select(['id', 'name'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->toBase()
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ]);
    }
}
