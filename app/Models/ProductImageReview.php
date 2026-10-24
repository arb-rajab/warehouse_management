<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductImageReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $created_at
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property string $status
 * @property string $image_source
 * @property string|null $image_url
 * @property string|null $note
 * @property-read User $user
 * @property-read Product $product
 */
#[Fillable(['product_id', 'user_id', 'status', 'image_source', 'image_url', 'note'])]
#[Table('wms_product_image_reviews')]
class ProductImageReview extends Model
{
    /** @use HasFactory<ProductImageReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The latest review is effective only for the image that was reviewed.
     * A new thumbnail or warehouse upload requires another manual review.
     *
     * @return Builder<self>
     */
    public static function currentStatusQuery(): Builder
    {
        return self::query()->select('status')
            ->whereColumn('product_id', 'products.id')
            ->whereRaw("image_source = COALESCE(products.warehouse_image_path, products.thumbnail_img, '')")
            ->whereRaw('id = (select max(latest.id) from wms_product_image_reviews latest where latest.product_id = products.id)')
            ->limit(1);
    }
}
