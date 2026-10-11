<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterClassifiedProductsRequest;
use App\Http\Requests\Store\Admin\PublishClassifiedProductRequest;
use App\Http\Resources\Store\ClassifiedProductResource;
use App\Models\CustomerProduct;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassifiedProductController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_classified_products', only: ['index']),
            new Middleware('permission:publish_classified_product', only: ['publish']),
            new Middleware('permission:delete_classified_product', only: ['destroy']),
        ];
    }

    public function index(FilterClassifiedProductsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $products = CustomerProduct::query()
            ->select(['id', 'name', 'slug', 'added_by', 'status', 'published', 'thumbnail_img'])
            ->with(['customer_product_translations:id,customer_product_id,lang,name'])
            ->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
        $uploads = Upload::query()->select(['id', 'file_name', 'external_link'])
            ->whereIn('id', $products->getCollection()->pluck('thumbnail_img')->filter())
            ->get()->keyBy('id');
        foreach ($products as $product) {
            $product->setAttribute('thumbnail_url', $uploads->get($product->thumbnail_img)?->url);
        }

        return Inertia::render('Store/Admin/Customers/ClassifiedProducts/Index', [
            'products' => $this->paginated(ClassifiedProductResource::collection($products)),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function publish(PublishClassifiedProductRequest $request, CustomerProduct $product): RedirectResponse
    {
        $product->published = (int) $request->boolean('published');
        $product->save();

        return $this->redirectPreservingQuery('classified_products', $request);
    }

    public function destroy(Request $request, CustomerProduct $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $product->customer_product_translations()->delete();
            $product->delete();
        });

        return $this->redirectPreservingQuery('classified_products', $request);
    }
}
