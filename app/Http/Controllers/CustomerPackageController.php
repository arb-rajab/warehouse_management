<?php

namespace App\Http\Controllers;

use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;
use App\Models\StoreUser;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Session;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerPackageController extends Controller implements HasMiddleware
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
            new Middleware('permission:view_classified_packages', only: ['index']),
            new Middleware('permission:edit_classified_package', only: ['edit']),
            new Middleware('permission:delete_classified_package', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $customer_packages = CustomerPackage::all();
        return view('shop.backend.customer.customer_packages.index', compact('customer_packages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('shop.backend.customer.customer_packages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $customer_package = new CustomerPackage;
        $customer_package->name = $request->name;
        $customer_package->amount = $request->amount;
        $customer_package->product_upload = $request->product_upload;
        $customer_package->logo = $request->logo;

        $customer_package->save();

        $customer_package_translation = CustomerPackageTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'customer_package_id' => $customer_package->id]);
        $customer_package_translation->name = $request->name;
        $customer_package_translation->save();


        flash(translate('Package has been inserted successfully'))->success();
        return redirect()->route('customer_packages.index');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $lang = $request->lang;
        $customer_package = CustomerPackage::findOrFail($id);
        return view('shop.backend.customer.customer_packages.edit', compact('customer_package', 'lang'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $customer_package = CustomerPackage::findOrFail($id);
        if ($request->lang == env("DEFAULT_LANGUAGE")) {
            $customer_package->name = $request->name;
        }
        $customer_package->amount = $request->amount;
        $customer_package->product_upload = $request->product_upload;
        $customer_package->logo = $request->logo;

        $customer_package->save();

        $customer_package_translation = CustomerPackageTranslation::firstOrNew(['lang' => $request->lang, 'customer_package_id' => $customer_package->id]);
        $customer_package_translation->name = $request->name;
        $customer_package_translation->save();

        flash(translate('Package has been updated successfully'))->success();
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        $customer_package = CustomerPackage::findOrFail($id);
        foreach ($customer_package->customer_package_translations as $key => $customer_package_translation) {
            $customer_package_translation->delete();
        }
        CustomerPackage::destroy($id);

        flash(translate('Package has been deleted successfully'))->success();
        return redirect()->route('customer_packages.index');
    }

    public function purchase_package(Request $request)
    {
        $data['customer_package_id'] = $request->customer_package_id;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'customer_package_payment');
        $request->session()->put('payment_data', $data);

        $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);

        if ($customer_package->amount == 0) {
            $user = StoreUser::findOrFail(Auth::user()->id);
            if ($user->customer_package_id != $customer_package->id) {
                return $this->purchase_payment_done(Session::get('payment_data'), null);
            } else {
                flash(translate('You can not purchase this package anymore.'))->warning();
                return back();
            }
        }

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
        }
    }

    public function purchase_payment_done($payment_data, $payment)
    {
        $user = StoreUser::findOrFail(Auth::user()->id);
        $user->customer_package_id = $payment_data['customer_package_id'];
        $customer_package = CustomerPackage::findOrFail($payment_data['customer_package_id']);
        $user->remaining_uploads += $customer_package->product_upload;
        $user->save();

        flash(translate('Package purchasing successful'))->success();
        return redirect()->route('dashboard');
    }
}
