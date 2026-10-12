<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsExport;
use App\Models\ProductsImport;
use App\Models\StoreUser;
use Auth;
use Excel;
use Illuminate\Http\Request;
use PDF;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductBulkUploadController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:product_bulk_import', only: ['index', 'bulk_upload', 'pdf_download_brand', 'pdf_download_category', 'pdf_download_seller']),
            new Middleware('permission:product_bulk_export', only: ['export']),
        ];
    }
    public function index()
    {
        if (Auth::user()->user_type == 'seller') {
            if(Auth::user()->shop->verification_status){
                return view('seller.product_bulk_upload.index');
            }
            else{
                flash(translate('Your shop is not verified yet!'))->warning();
                return back();
            }
        }
        elseif (Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'staff') {
            return view('shop.backend.product.bulk_upload.index');
        }
    }

    public function export(){
        return Excel::download(new ProductsExport, 'products.xlsx');
    }

    public function pdf_download_category()
    {
        $categories = Category::all();

        return PDF::loadView('shop.backend.downloads.category',[
            'categories' => $categories,
        ], [], [])->download('category.pdf');
    }

    public function pdf_download_brand()
    {
        $brands = Brand::all();

        return PDF::loadView('shop.backend.downloads.brand',[
            'brands' => $brands,
        ], [], [])->download('brands.pdf');
    }

    public function pdf_download_seller()
    {
        $users = StoreUser::where('user_type','seller')->get();

        return PDF::loadView('shop.backend.downloads.user',[
            'users' => $users,
        ], [], [])->download('user.pdf');

    }

    public function bulk_upload(Request $request)
    {
        if($request->hasFile('bulk_file')){
            $import = new ProductsImport;
            Excel::import($import, request()->file('bulk_file'));
        }

        return back();
    }

}
