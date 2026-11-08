<?php

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductBarcode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;

/**
 * A barcode value the WMS products page or the store product form may add to
 * `product_barcodes`, the one barcode table: once trimmed, no product may
 * already hold it (`validation.unique`), and it may not be the canonical id
 * of an existing product (`messages.barcode_is_product_id`), since every
 * product's id is already its default barcode and a scan resolves the id
 * first.
 *
 * A value `$holder` already holds passes as it is: the store edit form posts
 * the product's barcodes back, including legacy values another product shares
 * and the Otajer import's own-id row, which exist already and stay. The WMS
 * form passes no holder, so adding a value the product already has is "taken".
 * Each form keeps its own other rules (the WMS printable ASCII up to 64, the
 * store any text up to 255).
 *
 * The table has no unique index (existing data holds duplicates), so a writer
 * repeats this check and inserts under {@see self::locked()}: two admins adding
 * the same value at once cannot both pass.
 */
class NewProductBarcode implements ValidationRule
{
    /** The cache lock every writer of new barcodes holds around its final check and insert. */
    public const string LOCK = 'product-barcodes';

    /** @var list<string>|null the holder's values, trimmed, read once per rule */
    private ?array $held = null;

    public function __construct(private readonly ?Product $holder = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            return;
        }

        $value = trim((string) $value);

        if ($value === '' || in_array($value, $this->held(), true)) {
            return;
        }

        if (ProductBarcode::query()->where('barcode', $value)->exists()) {
            $fail('validation.unique')->translate();

            return;
        }

        if (self::isProductId($value)) {
            $fail('messages.barcode_is_product_id')->translate();
        }
    }

    /**
     * Runs $callback holding the shared barcode lock, waiting up to five
     * seconds for it. The caller repeats its validation inside, then inserts.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function locked(Closure $callback): mixed
    {
        return Cache::lock(self::LOCK, 10)->block(5, $callback);
    }

    /**
     * Only the canonical form of an id ("7", not "007" or "7.0") collides:
     * the default barcode is the id as plain digits, so that is the only
     * string a scan can match it by.
     */
    public static function isProductId(string $value): bool
    {
        if (preg_match('/^[1-9][0-9]{0,17}$/', $value) !== 1) {
            return false;
        }

        return Product::query()->whereKey((int) $value)->exists();
    }

    /** @return list<string> */
    private function held(): array
    {
        return $this->held ??= $this->holder === null
            ? []
            : array_values($this->holder->barcodes()->pluck('barcode')->map(fn (mixed $barcode): string => trim((string) $barcode))->all());
    }
}
