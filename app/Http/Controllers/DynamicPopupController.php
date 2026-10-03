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
            new Middleware('permission:view_all_dynamic_popups', only: ['index', 'show']),
            new Middleware('permission:add_dynamic_popups', only: ['create', 'store']),
            new Middleware('permission:edit_dynamic_popups', only: ['edit', 'update']),
            new Middleware('permission:delete_dynamic_popups', only: ['destroy', 'bulk_dynamic_popup_delete']),
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
        DynamicPopup::create($this->popupAttributes($request));
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
        $dynamic_popup->update($this->popupAttributes($request));
        flash(translate('Dynamic Popup has been updated successfully'))->success();
        return redirect()->route('dynamic-popups.index');
    }

    /**
     * The validated fields plus the button columns the server derives from the
     * chosen offer. Nothing else in the request reaches the model, so a forged
     * `status` or `btn_link` is ignored.
     *
     * @return array<string, mixed>
     */
    private function popupAttributes(DynamicPopupRequest $request): array
    {
        $validated = $request->validated();
        $offer = Offer::find($validated['offer_id']);

        return [
            ...$validated,
            'btn_link' => route('product', ['slug' => $offer?->base_product?->product->slug]),
            'btn_background_color' => '#000000',
            'btn_text_color' => 'white',
        ];
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
