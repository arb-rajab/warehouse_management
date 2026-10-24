<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImageReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImageReviewService
{
    /** @param array{image_review_status: string, image_source: ?string, image_review_note?: ?string} $data */
    public function record(int $productId, int $userId, array $data): ProductImageReview
    {
        return DB::transaction(function () use ($productId, $userId, $data) {
            $product = Product::query()->select(['id', 'thumbnail_img', 'warehouse_image_path'])
                ->with('thumbnailUpload:id,file_name,external_link')->lockForUpdate()->findOrFail($productId);

            if ((string) $product->image_url !== (string) $data['image_source']) {
                throw ValidationException::withMessages(['image_review_status' => __('messages.product_image_changed')]);
            }

            if (($product->image_url === null) !== ($data['image_review_status'] === 'image_missing')) {
                throw ValidationException::withMessages(['image_review_status' => __('messages.product_image_review_invalid')]);
            }

            $note = isset($data['image_review_note']) ? trim($data['image_review_note']) : '';

            return ProductImageReview::create([
                'product_id' => $productId,
                'user_id' => $userId,
                'status' => $data['image_review_status'],
                'image_source' => $product->imageReviewSource(),
                'image_url' => $product->image_url,
                'note' => $note === '' ? null : $note,
            ]);
        });
    }
}
