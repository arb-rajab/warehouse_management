<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\DigitalProductRequest;
use App\Services\Store\SellerDigitalProductWriter;
use App\Models\Category;
use App\Models\Product;
use App\Models\Upload;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DigitalProductController  extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $products = Product::query()->select(['id', 'name', 'slug', 'category_id', 'unit_price', 'published', 'featured'])->where('user_id', Auth::user()->id)->where('digital', 1)->with(['product_translations:id,product_id,lang,name', 'category:id,name', 'category.category_translations:id,category_id,lang,name'])->orderBy('created_at', 'desc')->paginate(10);
        return view('shop.seller.product.digitalproducts.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if(addon_is_activated('seller_subscription')){
            if(seller_package_validity_check()){
                return view('shop.seller.product.digitalproducts.create', $this->formOptions());
            }
            else {
                flash(__('seller.products.please_upgrade_your_package'))->warning();
                return back();
            }
        }
        return view('shop.seller.product.digitalproducts.create', $this->formOptions());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(DigitalProductRequest $request, SellerDigitalProductWriter $writer)
    {
        if (addon_is_activated('seller_subscription') && ! seller_package_validity_check()) {
            flash(__('seller.products.please_upgrade_your_package'))->warning();
            return redirect()->route('seller.digitalproducts');
        }
        $writer->create($request->user('store'), $request->productData());
        flash(__('seller.products.digital_product_has_been_inserted_successfully'))->success();
        return redirect()->route('seller.digitalproducts');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $lang = is_string($request->lang) ? $request->lang : config('app.default_language');
        $product = Product::findOrFail($id);
        if (Gate::denies('updateAsSeller', $product)) {
            flash(__('seller.products.this_product_is_not_yours'))->warning();
            return back();
        }

        $product->load(['product_translations', 'taxes']);
        return view('shop.seller.product.digitalproducts.edit', [...$this->formOptions(), 'product' => $product, 'lang' => $lang]);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'product' => null, 'lang' => config('app.default_language'),
            'categoryOptions' => Category::treeOptions(fn ($roots) => $roots->where('digital', 1)),
            'languages' => \App\Models\Language::query()->select(['id', 'code', 'name'])->get(),
            'taxes' => \App\Models\Tax::query()->select(['id', 'name'])->where('tax_status', 1)->get(),
        ];
    }

    public function update(DigitalProductRequest $request, Product $id, SellerDigitalProductWriter $writer)
    {
        if (Gate::denies('updateAsSeller', $id)) {
            flash(__('seller.products.this_product_is_not_yours'))->warning();
            return back();
        }
        $writer->update($request->user('store'), $id, $request->productData());
        flash(__('seller.products.digital_product_has_been_updated_successfully'))->success();
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        if (Gate::denies('updateAsSeller', $product)) {
            flash(__('seller.products.this_product_is_not_yours'))->warning();
            return back();
        }

        $product->product_translations()->delete();
        $product->stocks()->delete();

        Product::destroy($id);

        flash(__('storefront.product.product_has_been_deleted_successfully'))->success();
        return redirect()->route('seller.digitalproducts');
    }


    public function download(Request $request){
        $product = Product::findOrFail(decrypt($request->id));
        if(Auth::user()->id == $product->user_id){
            $upload = Upload::findOrFail($product->file_name);
            if (config('filesystems.default') == "s3") {
                return \Storage::disk('s3')->download($upload->file_name, $upload->file_original_name.".".$upload->extension);
            }
            else {
                if (file_exists(base_path('public/'.$upload->file_name))) {
                    return response()->download(base_path('public/'.$upload->file_name));
                }
            }
        }
        else {
            abort(404);
        }
    }
}
