<?php

namespace App\Http\Controllers;

use App\Http\Requests\DynamicPopupRequest;
use App\Models\DynamicPopup;
use App\Models\Offer;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DynamicPopupController extends Controller implements HasMiddleware
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
            new Middleware('permission:view_all_dynamic_popups', only: ['index']),
            new Middleware('permission:add_dynamic_popups', only: ['create']),
            new Middleware('permission:edit_dynamic_popups', only: ['edit']),
            new Middleware('permission:delete_dynamic_popups', only: ['destroy']),
            new Middleware('permission:publish_dynamic_popups', only: ['update_status']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $sort_search = null;
        $dynamic_popups = DynamicPopup::orderBy('id', 'asc');
        if ($request->has('search')){
            $sort_search = $request->search;
            $dynamic_popups = $dynamic_popups->where('title', 'like', '%'.$sort_search.'%');
        }
        $dynamic_popups = $dynamic_popups->paginate(15);
        return view('shop.backend.marketing.dynamic_popup.index', compact('dynamic_popups', 'sort_search'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('shop.backend.marketing.dynamic_popup.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(DynamicPopupRequest $request)
    {
        $offer = Offer::find($request->offer_id);
        $request->merge(['btn_link' => route('product', ['slug' => $offer?->base_product?->product->slug])]);
        $request->merge(['btn_background_color' => '#000000']);
        $request->merge(['btn_text_color' => 'white']);

        DynamicPopup::create($request->except('_token'));
        flash(translate('Dynamic Popup has been inserted successfully'))->success();
        return redirect()->route('dynamic-popups.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(DynamicPopup $dynamic_popup)
    {
        return view('shop.backend.marketing.dynamic_popup.edit', compact('dynamic_popup'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(DynamicPopupRequest $request, DynamicPopup $dynamic_popup)
    {
        $offer = Offer::find($request->offer_id);
        $request->merge(['btn_link' => route('product', ['slug' => $offer?->base_product?->product->slug])]);
        $request->merge(['btn_background_color' => '#000000']);
        $request->merge(['btn_text_color' => 'white']);
        $dynamic_popup->update($request->except(['_token','_method']));
        flash(translate('Dynamic Popup has been updated successfully'))->success();
        return redirect()->route('dynamic-popups.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        DynamicPopup::destroy($id);
        flash(translate('Dynamic Popup has been deleted successfully'))->success();
        return redirect()->route('dynamic-popups.index');
    }

    public function bulk_dynamic_popup_delete(Request $request)
    {
        DynamicPopup::whereIn('id', $request->id)->delete();
        return 1;
    }

    public function update_status(Request $request)
    {
        $dynamic_popup = DynamicPopup::findOrFail($request->id);
        $dynamic_popup->status = $request->status;
        if($dynamic_popup->save()){
            return 1;
        }
        return 0;
    }

}
