<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\PhysicalProductRequest;
use App\Http\Requests\Seller\PhysicalProductVariantsRequest;
use App\Services\Store\SellerProductWriter;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use Artisan;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Str;

class ProductController extends Controller
{
    protected $productTaxService;
    protected $productStockService;

    public function __construct(
        ProductTaxService $productTaxService,
        ProductStockService $productStockService
    ) {
        $this->productTaxService = $productTaxService;
        $this->productStockService = $productStockService;
    }

    public function index(Request $request)
    {
        $search = null;
        $products = Product::where('user_id', Auth::user()->id)->where('digital', 0)->where('auction_product', 0)->where('wholesale_product', 0)->orderBy('created_at', 'desc');
        if ($request->has('search')) {
            $search = $request->search;
            $products = $products->where('name', 'like', '%' . $search . '%');
        }
        $products = $products->with(['product_translations', 'category.category_translations', 'stocks'])->paginate(10);
        return view('shop.seller.product.products.index', compact('products', 'search'));
    }

    public function create()
    {
        if (addon_is_activated('seller_subscription') && ! seller_package_validity_check()) {
            flash(translate('Please upgrade your package.'))->warning();
            return back();
        }

        return view('shop.seller.product.products.create', $this->formOptions());
    }

    public function store(PhysicalProductRequest $request, SellerProductWriter $writer)
    {
        if (addon_is_activated('seller_subscription')) {
            if (!seller_package_validity_check()) {
                flash(translate('Please upgrade your package.'))->warning();
                return redirect()->route('seller.products');
            }
        }

        $writer->create($request->user('store'), $request->productData(), $request->variantNames());

        flash(translate('Product has been inserted successfully'))->success();

        return redirect()->route('seller.products');
    }

    public function edit(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if (Gate::denies('updateAsSeller', $product)) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        $lang = is_string($request->lang) ? $request->lang : config('app.default_language');
        return view('shop.seller.product.products.edit', [...$this->formOptions($product), 'product' => $product, 'lang' => $lang]);
    }

    public function update(PhysicalProductRequest $request, Product $product, SellerProductWriter $writer)
    {
        if (Gate::denies('updateAsSeller', $product)) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        $writer->update($request->user('store'), $product, $request->productData(), $request->variantNames());

        flash(translate('Product has been updated successfully'))->success();

        return back();
    }

    /** @return array<string, mixed> The same reference options for create and edit. */
    private function formOptions(?Product $product = null): array
    {
        $product?->load(['stocks', 'taxes', 'product_translations']);
        $unmatched = $product ? app(\App\Services\Store\ProductVariantCombinations::class)->unmatchedStocks($product) : [];

        return [
            'categoryOptions' => Category::treeOptions(fn ($roots) => $roots->where('digital', 0)),
            'brands' => \App\Models\Brand::query()->with('brand_translations')->get(),
            'colors' => \App\Models\Color::query()->orderBy('name')->get(),
            'attributes' => \App\Models\Attribute::query()->with(['attribute_values', 'attribute_translations'])->get(),
            'languages' => \App\Models\Language::query()->get(),
            'taxes' => \App\Models\Tax::query()->where('tax_status', 1)->get(),
            'unmatchedStocks' => $product ? $product->stocks->whereIn('variant', $unmatched) : collect(),
        ];
    }

    public function sku_combination(PhysicalProductVariantsRequest $request)
    {
        return view('shop.seller.product.products.variant_rows', ['names' => $request->variantNames(), 'postedVariants' => $request->validated(), 'unit_price' => $request->validated('unit_price')]);
    }

    public function sku_combination_edit(PhysicalProductVariantsRequest $request)
    {
        $product = Product::query()->where('user_id', $request->user('store')->id)->with('stocks')->findOrFail($request->validated('id'));

        return view('shop.seller.product.products.variant_rows', ['names' => $request->variantNames(), 'postedVariants' => $request->validated(), 'unit_price' => $request->validated('unit_price'), 'product' => $product]);
    }

    public function add_more_choice_option(Request $request)
    {
        $data = $request->validate(['attribute_id' => ['required', 'integer', 'exists:attributes,id']]);
        $all_attribute_values = AttributeValue::query()->where('attribute_id', $data['attribute_id'])->get();

        $html = '';

        foreach ($all_attribute_values as $row) {
            $html .= '<option value="' . e($row->value) . '">' . e($row->value) . '</option>';
        }

        return response()->json($html);
    }

    public function updatePublished(Request $request)
    {
        $product = Product::findOrFail($request->id);
        if (Gate::denies('updateAsSeller', $product)) {
            return 0;
        }
        $product->published = $request->status;
        if (addon_is_activated('seller_subscription') && $request->status == 1) {
            $shop = $product->user->shop;
            if (
                $shop->package_invalid_at == null
                || (int) Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
                || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
            ) {
                return 2;
            }
        }
        $product->save();
        return 1;
    }

    public function updateFeatured(Request $request)
    {
        $product = Product::findOrFail($request->id);
        if (Gate::denies('updateAsSeller', $product)) {
            return 0;
        }
        $product->seller_featured = $request->status;
        if ($product->save()) {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            return 1;
        }
        return 0;
    }

    public function duplicate($id)
    {
        $product = Product::find($id);
        if (Gate::denies('updateAsSeller', $product)) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }
        if (addon_is_activated('seller_subscription')) {
            if (!seller_package_validity_check()) {
                flash(translate('Please upgrade your package.'))->warning();
                return back();
            }
        }

        if (Auth::user()->id == $product->user_id) {
            $product_new = $product->replicate();
            $product_new->slug = $product_new->slug . '-' . Str::random(5);
            $product_new->save();

            //Product Stock
            $this->productStockService->product_duplicate_store($product->stocks, $product_new);

            //VAT & Tax
            $this->productTaxService->product_duplicate_store($product->taxes, $product_new);

            flash(translate('Product has been duplicated successfully'))->success();
            return redirect()->route('seller.products');
        } else {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if (Gate::denies('updateAsSeller', $product)) {
            flash(translate('This product is not yours.'))->warning();
            return back();
        }

        $product->product_translations()->delete();
        $product->stocks()->delete();
        $product->taxes()->delete();


        if (Product::destroy($id)) {
            Cart::where('product_id', $id)->delete();

            flash(translate('Product has been deleted successfully'))->success();

            Artisan::call('view:clear');
            Artisan::call('cache:clear');

            return back();
        } else {
            flash(translate('Something went wrong'))->error();
            return back();
        }
    }
}
