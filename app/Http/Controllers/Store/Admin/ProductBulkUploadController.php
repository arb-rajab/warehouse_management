<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\ImportProductsRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsExport;
use App\Models\ProductsImport;
use App\Models\StoreUser;
use App\Services\ProductSpreadsheetImporter;
use App\Support\StoreLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProductBulkUploadController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:product_bulk_import', except: ['export']),
            new Middleware('permission:product_bulk_export', only: ['export']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Store/Admin/Product/BulkUpload/Index', [
            'exampleUrl' => static_asset('download/product_bulk_demo.xlsx'),
        ]);
    }

    public function bulkUpload(ImportProductsRequest $request): RedirectResponse
    {
        if (! StoreUser::query()->where('user_type', 'admin')->exists()) {
            throw ValidationException::withMessages(['bulk_file' => __('messages.store_product_import_admin_required')]);
        }

        app(ProductSpreadsheetImporter::class)->import(new ProductsImport(notify: false), $request->file('bulk_file'));

        return redirect()->route('product_bulk_upload.index');
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new ProductsExport, 'products.xlsx');
    }

    public function pdfDownloadCategory(): HttpResponse
    {
        return PDF::loadView('shop.backend.downloads.category', [
            'documentLocale' => StoreLocale::documentLocale(),
            'categories' => Category::query()->select(['id', 'name'])->with('category_translations:id,category_id,name,lang')->orderBy('id')->get(),
        ], [], [])->download('category.pdf');
    }

    public function pdfDownloadBrand(): HttpResponse
    {
        return PDF::loadView('shop.backend.downloads.brand', [
            'documentLocale' => StoreLocale::documentLocale(),
            'brands' => Brand::query()->select(['id', 'name'])->with('brand_translations:id,brand_id,name,lang')->orderBy('id')->get(),
        ], [], [])->download('brands.pdf');
    }

    public function pdfDownloadSeller(): HttpResponse
    {
        return PDF::loadView('shop.backend.downloads.user', [
            'documentLocale' => StoreLocale::documentLocale(),
            'users' => StoreUser::query()->select(['id', 'name', 'email', 'user_type'])->where('user_type', 'seller')->orderBy('id')->get(),
        ], [], [])->download('user.pdf');
    }
}
