<?php

namespace App\Http\Controllers\api\v2;

use App\Http\Controllers\Controller;
use App\Models\OffersCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class OffersCatalogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\OffersCatalog  $offersCatalog
     * @return \Illuminate\Http\Response
     */
    public function show(OffersCatalog $offersCatalog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OffersCatalog  $offersCatalog
     * @return \Illuminate\Http\Response
     */
    public function edit(OffersCatalog $offersCatalog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OffersCatalog  $offersCatalog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OffersCatalog $offersCatalog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OffersCatalog  $offersCatalog
     * @return \Illuminate\Http\Response
     */
    public function destroy(OffersCatalog $offersCatalog)
    {
        //
    }

    public function download()
    {
        $offer = OffersCatalog::first();
        if (isset($offer)) {

            $filename = $offer->file;
            $path = public_path('uploads/offers/catalogs/' . $filename);

            if (!file_exists($path)) {
                abort(404);
            }

            Log::info(asset($path));

            return response()->download($path, $filename);
        } else {
            return response()->json([
                'success' => false,
                'message' => "No Offers Available"
            ], 404);
        }
    }
}
