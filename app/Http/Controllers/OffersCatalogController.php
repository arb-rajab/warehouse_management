<?php

namespace App\Http\Controllers;

use App\Models\OffersCatalog;
use App\Services\OffersCatalogStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OffersCatalogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $offers = OffersCatalog::all();

        return view('shop.backend.offers_catalog.index', compact('offers'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request, OffersCatalogStorage $catalogs)
    {
        $offers = OffersCatalog::first();
        if($offers){ // if there is already a pdf catalog
            flash(translate('Only one catalog can be uploaded'))->error();
            return back();
        }
        else{
            if (request()->hasFile('catalog')) {
                OffersCatalog::create([
                    'file' => $catalogs->store(request()->file('catalog')),
                ]);
                flash(translate('Offer Catalog Uploaded Successfully'))->success();
                return back();
            }
            else{
                flash(translate('Error!'))->error();
                return back();
            }


        }
    }


    public function download(OffersCatalogStorage $catalogs)
    {
        return $catalogs->download(OffersCatalog::first()?->file) ?? abort(404);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  OffersCatalog  $offersCatalog
     * @return Response
     */
    public function destroy($catalog_id, OffersCatalogStorage $catalogs)
    {
        $offer = OffersCatalog::findOrFail($catalog_id);

        $catalogs->delete($offer->file);
        $offer->delete();
        flash(translate('Offer Catalog Deleted Successfully'))->success();

        return back();
    }
}
