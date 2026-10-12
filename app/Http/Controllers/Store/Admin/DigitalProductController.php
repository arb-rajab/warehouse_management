<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\UpdatesStoreProductFlags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterDigitalProductsRequest;
use App\Http\Requests\Store\Admin\UpdateDigitalProductFlagRequest;
use App\Http\Resources\Store\DigitalProductResource;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The digital products list and its row actions. Creating and editing a
 * digital product stay on the root DigitalProductController's Blade forms,
 * which need the rich-text editor.
 */
class DigitalProductController extends Controller implements HasMiddleware
{
    use UpdatesStoreProductFlags;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:show_digital_products', only: ['index']),
            new Middleware('permission:delete_digital_product', only: ['destroy']),
            new Middleware('permission:download_digital_product', only: ['download']),
            // The list's switches used ProductController's shared endpoints,
            // which product_edit gates; these keep that gate.
            new Middleware('permission:product_edit', only: ['updatePublished', 'updateFeatured', 'updateTodaysDeal']),
        ];
    }

    public function index(FilterDigitalProductsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Product/DigitalProducts/Index', [
            'products' => $this->paginated(DigitalProductResource::collection(
                Product::query()->digital()
                    ->select(['id', 'name', 'added_by', 'thumbnail_img', 'unit_price', 'todays_deal', 'published', 'featured', 'slug', 'created_at'])
                    ->with('product_translations:id,product_id,lang,name')
                    ->when($search !== null, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    /**
     * Deleting a digital product still removes its translations and stock
     * rows and then hard-deletes the `products` row, as the legacy handler
     * did; the three statements now commit or fail together.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product): void {
            $product = $this->lockedDigitalProduct($product);
            $product->product_translations()->delete();
            $product->stocks()->delete();
            $product->delete();
        });

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    /**
     * The legacy route still takes the encrypted product id. A malformed id
     * or a file missing from local storage is a 404 rather than the legacy
     * 500 or empty 200.
     */
    public function download(string $id): BinaryFileResponse|StreamedResponse
    {
        try {
            $productId = filter_var(decrypt($id), FILTER_VALIDATE_INT);
        } catch (DecryptException) {
            abort(404);
        }
        abort_if($productId === false, 404);

        $product = Product::query()->digital()->select(['id', 'file_name'])->findOrFail($productId);
        $uploadId = filter_var($product->getAttribute('file_name'), FILTER_VALIDATE_INT);
        abort_if($uploadId === false, 404);
        $upload = Upload::query()->select(['id', 'file_name', 'file_original_name', 'extension'])->findOrFail($uploadId);

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->download((string) $upload->file_name, $upload->file_original_name.'.'.$upload->extension);
        }

        $path = base_path('public/'.$upload->file_name);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    /**
     * Writes as ProductController::updatePublished() does, through the
     * switch logic the product lists share (UpdatesStoreProductFlags).
     */
    public function updatePublished(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductPublished(Product::query()->digital(), $product, $request->boolean('status'));

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    public function updateFeatured(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        $this->updateProductFeatured(Product::query()->digital(), $product, $request->boolean('status'));

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    public function updateTodaysDeal(UpdateDigitalProductFlagRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product = $this->lockedDigitalProduct($product);
            $product->setAttribute('todays_deal', (int) $request->boolean('status'));
            $product->save();
        });
        Cache::forget('todays_deal_products');

        return $this->redirectPreservingQuery('digitalproducts.index', $request);
    }

    /**
     * The bound product, locked for the surrounding transaction; anything
     * but a digital product is a 404 here.
     */
    private function lockedDigitalProduct(Product $product): Product
    {
        return $this->lockedProduct(Product::query()->digital(), $product);
    }
}
