<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OffersCatalogDownloadController extends Controller
{
    public function __invoke(OffersCatalogStorage $catalogs): BinaryFileResponse
    {
        return $catalogs->download(OffersCatalog::query()->select('file')->orderBy('id')->first()?->file) ?? abort(404);
    }
}
