<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\DynamicPopupCollection;
use App\Models\DynamicPopup;
use App\Services\DynamicPopupService;
use Auth;
use Illuminate\Http\Request;

class DynamicPopupController extends Controller
{
    protected $dynamicPopupService;

    protected function dynamicPopupService()
    {
        return app(DynamicPopupService::class);
    }

    public function index(Request $request)
    {
        $sort_search = null;

        if(Auth::check() && !Auth::user()->is_rep){
            $dynamic_popups = DynamicPopup::with('offer')->forAuthUser()->orderBy('id', 'asc')->where('status', 1);
            if ($request->has('search')){
                $sort_search = $request->search;
                $dynamic_popups = $dynamic_popups->where('title', 'like', '%'.$sort_search.'%');
            }

            if($request->has('show_page')){
                $show_page = $request->show_page;
                $dynamic_popups = $dynamic_popups->where('show_page', $show_page);
            }
            $dynamic_popups = $dynamic_popups->get();
        }else{
            $dynamic_popups = DynamicPopup::where('id', 0)->get();
        }

        return new DynamicPopupCollection($dynamic_popups);
    }

    public function ignore($id)
    {
        try {

            $dynamic_popup = DynamicPopup::find($id);
            if(!$dynamic_popup){
                return response()->json([
                    'success' => false,
                    'message' => translate('Record not found')
                ], 404);
            }
            $next_popups = $this->dynamicPopupService()->getNextPopups($dynamic_popup);

            return new DynamicPopupCollection($next_popups);


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500); // Internal Server Error
        }
    }
}
