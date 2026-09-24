<?php

namespace App\Http\Controllers;

use App\Models\OffersCatalog;
use Illuminate\Http\Request;

class OffersCatalogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $offers = OffersCatalog::all();

        return view('backend.offers_catalog.index', compact('offers'));
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
        $offers = OffersCatalog::first();
        if($offers){ // if there is already a pdf catalog
            flash(translate('Only one catalog can be uploaded'))->error();
            return back();
        }
        else{
            if (request()->hasFile('catalog')) {
                $uploadedFile = request()->file('catalog');
                 $ImageFileName = $uploadedFile->getClientOriginalName(); // Get the original filename

                $ImageFileName = preg_replace('/\s+/', '', $ImageFileName);
                // Store the PDF file in the 'local' disk with the specified filename
                $uploadedFile->storeAs('uploads/offers/catalogs', $ImageFileName,'local');


                OffersCatalog::create([
                    'file' => $ImageFileName
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


    public function download(){
        $offer = OffersCatalog::first();
        // Retrieve the picture file from the folder
            $filename = $offer->file;
            $path = public_path('uploads/offers/catalogs/' . $filename);

            // Check if the file exists
            if (!file_exists($path)) {
                abort(404);
            }
            // choose a filename for the downloaded file // optional
            $downloadFilename = $filename;
            // Create a download response with the file contents and headers
            return response()->download($path, $downloadFilename);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OffersCatalog  $offersCatalog
     * @return \Illuminate\Http\Response
     */
    public function destroy($catalog_id)
    {
        $offer = OffersCatalog::find($catalog_id);
        $file_path = public_path('uploads/offers/catalogs/' . $offer->file);

        if(unlink($file_path)){
            $offer->delete();
            flash(translate('Offer Catalog Deleted Successfully'))->success();
            return back();
        }

        else{
            flash(translate('Error! Try again.'))->error();
            return back();
        }



    }
}
