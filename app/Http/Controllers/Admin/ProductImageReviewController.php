<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterProductImageReviewsRequest;
use App\Http\Requests\Admin\StoreProductImageReviewRequest;
use App\Http\Requests\Admin\UpdateProductImageRequest;
use App\Models\Product;
use App\Models\ProductImageReview;
use App\Services\ProductImageReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use RuntimeException;
use Throwable;

class ProductImageReviewController extends Controller
{
    public function show(FilterProductImageReviewsRequest $request, Product $product): JsonResponse
    {
        $product->load(['thumbnailUpload:id,file_name,external_link', 'barcodes:id,product_id,barcode']);
        $reviews = ProductImageReview::query()
            ->select(['id', 'product_id', 'user_id', 'status', 'note', 'image_url', 'created_at'])
            ->where('product_id', $product->id)->with('user:id,name,deleted_at')
            ->orderByDesc('id')->paginate(10);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'ar_name' => $product->ar_name,
            'image_url' => $product->image_url,
            'image_source' => $product->image_url ?? '',
            'barcodes' => $product->barcodes->pluck('barcode'),
            'reviews' => $reviews->through(fn (ProductImageReview $review): array => [
                'id' => $review->id,
                'status' => $review->status,
                'note' => $review->note,
                'image_url' => $review->image_url,
                'user' => ['id' => $review->user->id, 'name' => $review->user->name],
                'created_at' => $review->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(StoreProductImageReviewRequest $request, Product $product, ProductImageReviewService $service): RedirectResponse
    {
        $service->record($product->id, (int) $request->user()?->getAuthIdentifier(), $request->imageReviewData());

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }

    public function updateImage(UpdateProductImageRequest $request, Product $product): RedirectResponse
    {
        $image = Image::make($request->file('image')->getRealPath());
        if (function_exists('exif_read_data')) {
            $image->orientate();
        }
        $image->resize(1600, 1600, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        })->encode('jpg', 90);
        $path = 'product-reference-images/'.Str::uuid().'.jpg';

        if (! Storage::disk('public')->put($path, (string) $image)) {
            throw new RuntimeException('Unable to store product reference image.');
        }

        try {
            DB::transaction(function () use ($product, $path) {
                $locked = Product::query()->select(['id'])->lockForUpdate()->findOrFail($product->id);
                $locked->forceFill(['warehouse_image_path' => $path])->save();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return $this->redirectPreservingQuery('admin.products.index', $request);
    }
}
