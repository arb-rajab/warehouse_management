<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;


class OffersCatalogController extends Controller
{
    public function download(OffersCatalogStorage $catalogs)
    {
        $offer = OffersCatalog::first();

        if ($offer === null) {
            return response()->json([
                'success' => false,
                'message' => "No Offers Available"
            ], 404);
        }

        return $catalogs->download($offer->file) ?? abort(404);
    }
}
