<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterClassifiedProductRequest;
use App\Http\Requests\Store\Admin\UpdateClassifiedProductPublishedRequest;
use App\Http\Resources\Store\ClassifiedProductResource;
use App\Models\CustomerProduct;
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
            new Middleware('permission:view_classified_products', only: ['customer_product_index']),
            new Middleware('permission:publish_classified_product', only: ['updatePublished']),
            new Middleware('permission:delete_classified_product', only: ['destroy_by_admin']),
        ];
    }

    public function customer_product_index(FilterClassifiedProductRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $products = CustomerProduct::query()
            ->select(['id', 'name', 'slug', 'thumbnail_img', 'added_by', 'status', 'published'])
            ->with(['customer_product_translations:id,customer_product_id,lang,name', 'thumbnailUpload:id,file_name,external_link'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        return Inertia::render('Store/Admin/Customers/ClassifiedProducts/Index', [
            'products' => $this->paginated(ClassifiedProductResource::collection($products)),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function updatePublished(UpdateClassifiedProductPublishedRequest $request, CustomerProduct $classifiedProduct): RedirectResponse
    {
        $classifiedProduct->setAttribute('published', (int) $request->boolean('status'))->save();

        return $this->redirectPreservingQuery('classified_products', $request);
    }

    public function destroy_by_admin(Request $request, CustomerProduct $classifiedProduct): RedirectResponse
    {
        DB::transaction(function () use ($classifiedProduct): void {
            $classifiedProduct->customer_product_translations()->delete();
            $classifiedProduct->delete();
        });

        return $this->redirectPreservingQuery('classified_products', $request);
    }
}
