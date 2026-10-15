<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterReviewsRequest;
use App\Http\Requests\Store\Admin\UpdateReviewPublishedRequest;
use App\Http\Resources\Store\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_product_reviews', only: ['index']),
            new Middleware('permission:publish_product_review', only: ['updatePublished']),
        ];
    }

    public function index(FilterReviewsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $rating = $request->validated('rating');
        $reviews = Review::query()
            ->select(['id', 'product_id', 'user_id', 'rating', 'comment', 'status'])
            ->has('product')->has('user')
            ->with(['product:id,name,slug,added_by', 'product.product_translations:id,product_id,name,lang', 'user:id,name,email'])
            ->when($rating, fn ($query) => $query->orderBy('rating', $rating === 'rating,asc' ? 'asc' : 'desc'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Store/Admin/Product/Reviews/Index', [
            'reviews' => $this->paginated(ReviewResource::collection($reviews)),
            'filters' => ['per_page' => $perPage, 'rating' => $rating],
        ]);
    }

    public function updatePublished(UpdateReviewPublishedRequest $request, Review $review): RedirectResponse
    {
        DB::transaction(function () use ($request, $review): void {
            $product = Product::query()->select(['id', 'added_by', 'user_id', 'rating'])->lockForUpdate()->whereKey($review->getAttribute('product_id'))->firstOrFail();
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            $status = $request->boolean('status');
            if ((bool) $review->getAttribute('status') === $status) {
                return;
            }

            $review->setAttribute('status', (int) $status)->save();
            $product->setAttribute('rating', Review::query()->where('product_id', $product->id)->where('status', 1)->avg('rating') ?? 0)->save();

            if ($product->getAttribute('added_by') === 'seller') {
                $shop = Shop::query()->without('user')->select(['id', 'user_id', 'rating', 'num_of_reviews'])->where('user_id', $product->user_id)->lockForUpdate()->first();
                if ($shop !== null) {
                    $count = (int) $shop->getAttribute('num_of_reviews');
                    $sum = (float) $shop->getAttribute('rating') * $count;
                    $delta = $status ? 1 : -1;
                    $newCount = max(0, $count + $delta);
                    $shop->setAttribute('num_of_reviews', $newCount);
                    $shop->setAttribute('rating', $newCount > 0 ? max(0, ($sum + $delta * (int) $review->getAttribute('rating')) / $newCount) : 0);
                    $shop->save();
                }
            }
        });

        return $this->redirectPreservingQuery('admin.reviews.index', $request);
    }
}
