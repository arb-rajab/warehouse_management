<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterOffersCatalogRequest;
use App\Http\Requests\Store\Admin\StoreOffersCatalogRequest;
use App\Http\Resources\Store\OffersCatalogResource;
use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OffersCatalogController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_offers_catalog', only: ['index', 'download']),
            new Middleware('permission:add_offers_catalog', only: ['store']),
            new Middleware('permission:delete_offers_catalog', only: ['destroy']),
        ];
    }

    public function index(FilterOffersCatalogRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Catalogue/Offers/Index', [
            'catalogs' => $this->paginated(OffersCatalogResource::collection(
                OffersCatalog::query()->select(['id', 'created_at'])->orderByDesc('id')
                    ->paginate($perPage)->withQueryString()
            )),
            'hasCatalog' => OffersCatalog::query()->exists(),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function store(StoreOffersCatalogRequest $request, OffersCatalogStorage $catalogs): RedirectResponse
    {
        DB::transaction(function () use ($request, $catalogs): void {
            if (OffersCatalog::query()->select('id')->lockForUpdate()->first() !== null) {
                throw ValidationException::withMessages(['catalog' => __('messages.store_offers_catalog_exists')]);
            }

            $file = $request->file('catalog');
            abort_unless($file instanceof UploadedFile, 422);
            $name = $catalogs->store($file);

            try {
                $catalog = new OffersCatalog;
                $catalog->file = $name;
                $catalog->save();
            } catch (\Throwable $exception) {
                $catalogs->delete($name);
                throw $exception;
            }
        });

        return redirect()->route('offers.index');
    }

    public function download(OffersCatalogStorage $catalogs): BinaryFileResponse
    {
        return $catalogs->download(OffersCatalog::query()->select('file')->orderBy('id')->first()?->file) ?? abort(404);
    }

    public function destroy(Request $request, OffersCatalog $catalog, OffersCatalogStorage $catalogs): RedirectResponse
    {
        $catalogs->delete($catalog->file);
        $catalog->delete();

        return $this->redirectPreservingQuery('offers.index', $request);
    }
}
